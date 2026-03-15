# ADR-0001: Core Architecture of mistericy/storage

## Status

Accepted

## Date

2026-03-15

## Context

`mistericy/storage` is a PHP library providing a unified storage and filesystem abstraction over multiple backends. The initial target adapters are **Local Filesystem**, **AWS S3**, **In-Memory**, and **FTP**.

The library must be consumed by projects ranging from greenfield PHP 8.x applications to legacy codebases. The primary design tension is between offering a rich, ergonomic abstraction and maintaining a lean, zero-dependency core that does not impose transitive dependencies on consumers who only need, for example, local disk access.

The library is also intended to be a stable foundation that third-party packages can extend by shipping their own adapters, without needing to modify this package.

## Decision Drivers

- **Zero mandatory dependencies** — the core must be self-contained; optional adapter dependencies must not be forced on all consumers.
- **PHP 8.0 minimum** — for compatibility with legacy projects; no PHP 8.1+ language features may be used in the core or bundled adapters.
- **Stream-first content model** — file content is exchanged as PHP `resource` (streams) throughout the entire public API.
- **SOLID compliance** — ISP governs interface granularity; DIP ensures consumers depend on abstractions; OCP ensures new adapters require no changes to the core.
- **Domain-Driven vocabulary** — Value Objects are preferred over primitives; the API communicates intent, not just data.
- **Testability** — the design must support trivial unit testing via adapter substitution (Strategy Pattern).

---

## Decisions

### 1. Entry Point: The `Filesystem` Facade (Strategy Pattern)

The single consumer-facing entry point is the `Filesystem` class. It receives an adapter via constructor injection and acts as the unified facade for all storage operations. Consumers type-hint against `Filesystem` or against individual capability interfaces (see §3).

```php
$fs = new Filesystem(new LocalAdapter('/var/storage'));
$stream = $fs->read(new Path('/uploads/avatar.jpg'));
```

The injected adapter is the **Strategy**; swapping backends requires only injecting a different adapter:

```php
$fs = new Filesystem(new InMemoryAdapter()); // for tests or ephemeral use
```

**Rationale:** A single-facade strategy keeps the API surface minimal and is trivially compatible with any PSR-11 DI container via constructor injection. It avoids the service-locator risks of a named-disk registry.

A `StorageManager` (registry of named disks) is explicitly deferred to a future release. The current design does not preclude it.

---

### 2. Adapter Interface Composition (Interface Segregation Principle)

Adapter capabilities are expressed as a set of **focused, cohesive interfaces** — not a single monolith and not one interface per method. The groupings balance expressiveness with pragmatism:

| Interface | Methods | Typical Adapters |
|---|---|---|
| `ReadableInterface` | `read(Path): resource` | All |
| `WritableInterface` | `write(Path, resource): void`, `delete(Path): void` | All |
| `InspectableInterface` | `exists(Path): bool`, `isFile(Path): bool`, `isDir(Path): bool` | All |
| `TransferableInterface` | `copy(Path, Path): void`, `move(Path, Path): void`, `rename(Path, Path): void` | Local, FTP, InMemory |
| `ListableInterface` | `listContents(Path): iterable<Path>` | All |
| `DirectoryInterface` | `createDirectory(Path): void`, `deleteDirectory(Path): void` | Local, FTP, InMemory |
| `StatableInterface` | `metadata(Path): FileMetadata` | All |

All adapter-facing interfaces extend a common marker `AdapterInterface`.

`Filesystem` **implements every capability interface** and proxies each method call to the underlying adapter. Before delegating, it checks whether the adapter implements the required capability interface. If it does not, `Filesystem` throws `OperationNotSupportedException`.

Consumers may perform runtime capability detection using `instanceof`:

```php
if ($fs instanceof ListableInterface) {
    $entries = $fs->listContents(new Path('/uploads'));
}
```

**Rationale:** Adapters only implement what they genuinely support — no forced stubs or silent no-ops. The `instanceof` pattern is ISP in practice: capability is expressed at the type level.

**Cross-adapter operations** (e.g. copy from a `LocalAdapter` instance to an `S3Adapter` instance) are **explicitly out of scope for v0.1** and must not be partially designed in. They will be addressed in a future ADR.

---

### 3. Value Objects as the Canonical Domain Currency

Primitives are avoided in favour of immutable **Value Objects**. Key types for v0.1:

#### `Path`
- Immutable, normalised string path (e.g. `/uploads/avatar.jpg`).
- No awareness of the underlying adapter or storage backend.
- Normalisation rules: forward-slash separator, no trailing slash, no double slashes.
- The library ships an `AbstractPath` plus a concrete `Path` implementation. Consumers may substitute their own implementation of `AbstractPath`.

```php
$path = new Path('/uploads/../uploads/avatar.jpg'); // normalised to /uploads/avatar.jpg
```

#### `FileMetadata`
- Immutable VO returned by `StatableInterface::metadata()`.
- Carries: file size (bytes), last modified timestamp, MIME type (where detectable), and an open-ended map of adapter-specific extras.
- Absent fields (e.g. MIME type on FTP) are represented as `null`, never omitted or thrown.

**Rationale (DDD):** Value Objects enforce invariants at construction, communicate intent through their type, and are safe to pass across boundaries without defensive copying.

---

### 4. Stream-First Content Model

All file content is exchanged exclusively as PHP `resource` (stream handles). There is no string-content overload of `read()` or `write()`. This is a **hard constraint** enforced at the interface level.

```php
// Write
$stream = fopen('php://temp', 'r+');
fwrite($stream, 'hello');
rewind($stream);
$fs->write(new Path('/greet.txt'), $stream);

// Read
$resource = $fs->read(new Path('/greet.txt'));
echo stream_get_contents($resource);
```

**Rationale:** Streams handle arbitrarily large files without exhausting memory, are natively supported across all four target adapters, and are the idiomatic PHP I/O primitive. A helper utility for converting strings to streams may be provided as a convenience but will never replace the stream-native interface.

---

### 5. Exception Hierarchy

All error conditions are communicated exclusively via exceptions — no `null` returns, no result wrappers, no error codes. The hierarchy is rooted at `StorageException`:

```
\RuntimeException
└── StorageException                    (root; catch-all for all library errors)
    ├── FileNotFoundException            (target file does not exist)
    ├── DirectoryNotFoundException       (target directory does not exist)
    ├── FileAlreadyExistsException       (write/copy/move to existing path, no overwrite)
    ├── PermissionDeniedException        (access denied by the backend)
    ├── OperationNotSupportedException   (adapter does not support this capability)
    └── AdapterException                 (wraps unexpected low-level adapter / IO failures)
```

**Rationale:** A rooted hierarchy allows consumers to catch at any granularity. `AdapterException` acts as an escape hatch for unexpected backend failures that do not fit a more specific type, preserving the original cause via `$previous`.

---

### 6. Adapter Configuration via Constructor Arguments

Each adapter is configured exclusively through **typed constructor arguments**. Where the number of arguments would be unwieldy (e.g. S3), a typed configuration Value Object is used (e.g. `S3Config`, `FtpConfig`) rather than a generic key-value bag.

```php
$config = new S3Config(
    bucket: 'my-bucket',
    region: 'eu-west-1',
    credentials: new AwsCredentials($key, $secret),
);
$fs = new Filesystem(new S3Adapter($config));
```

**Rationale:** Constructor injection is explicit, statically analysable by PHPStan, and natively compatible with PSR-11 DI containers. Configuration VOs make adapter setup self-documenting.

---

### 7. Optional Integrations

The core library — interfaces, `Filesystem`, Value Objects, and exceptions — has **zero `require` dependencies** in `composer.json`. All optional integrations are expressed via `suggest`.

| Concern | Strategy |
|---|---|
| **AWS S3 Adapter** | `aws/aws-sdk-php` listed under `suggest`; required only by consumers who instantiate `S3Adapter` |
| **PSR-3 Logging** | `LoggerInterface` accepted as an optional constructor argument on `Filesystem`; guarded by `interface_exists` at runtime; no hard `require` on `psr/log` |
| **PSR-11 DI Container** | No integration in v0.1; naturally compatible by constructor injection design |

---

### 8. Target PHP Version

The library targets **PHP 8.0** as a hard minimum. PHP 8.1+ language features — including enums, `readonly` properties, fibers, intersection types, and the `never` return type — **must not** be used in the core package or bundled adapters.

Value Object immutability is achieved through private properties and no mutating public methods, without relying on `readonly`.

This decision may be revisited and raised to PHP 8.1 in a future minor release.

---

## Out of Scope for v0.1

The following are **explicitly deferred** and must not be partially or speculatively implemented:

| Deferred Concern | Future ADR |
|---|---|
| Cross-adapter copy/move (Local → S3) | ADR-0002 (planned) |
| `StorageManager` / named-disk registry | ADR-0003 (planned) |
| File visibility / access control (public/private) | TBD |
| Abstract contract test suite for third-party adapter authors | TBD |
| Streaming chunked / multipart upload abstraction | TBD |
| URL generation (e.g. pre-signed S3 URLs) | TBD |
| PSR-11 container integration | TBD |

---

## Consequences

### Positive

- The library is immediately usable with zero Composer dependencies for `LocalAdapter` and `InMemoryAdapter`.
- Strategy pattern injection makes unit testing trivial — swap any adapter for `InMemoryAdapter` in the test suite.
- Interface Segregation means third-party adapters implement only what they support; the design is genuinely Open/Closed for new backends.
- A well-typed Value Object model (`Path`, `FileMetadata`, config VOs) makes the library statically analysable at the highest PHPStan levels.
- The rooted exception hierarchy gives consumers fine-grained or coarse-grained error handling without boilerplate.

### Negative / Trade-offs

- `Filesystem` is a **class**, not an interface. Consumers who want to mock it at the type level must depend on a capability interface rather than `Filesystem` directly. This must be clearly documented.
- PHP 8.0 target forbids `readonly` properties; Value Object immutability is enforced by convention (private properties, no setters) rather than language enforcement — PHPStan or architecture tests should compensate.
- Without a contract test suite, correctness of third-party adapters cannot be formally verified until that scope is added in a future release.
- The stream-only content model may feel verbose for trivial string reads/writes; a convenience utility layer should be considered for the documentation examples.

---

## References

- [Refactoring Guru — Strategy Pattern](https://refactoring.guru/design-patterns/strategy)
- [Refactoring Guru — Value Object (DDD)](https://refactoring.guru/design-patterns/value-objects)
- [PHP-FIG PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [PHP-FIG PSR-11: Container Interface](https://www.php-fig.org/psr/psr-11/)
- [SOLID Principles — Interface Segregation](https://en.wikipedia.org/wiki/Interface_segregation_principle)
