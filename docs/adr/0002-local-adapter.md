# ADR-0002: LocalAdapter — PHP Native Filesystem I/O

## Status

Proposed

## Date

2026-03-15

## Context

ADR-0001 established `InMemoryAdapter` as the first concrete adapter and
deferred the `LocalAdapter` to a follow-up decision. The local filesystem is
the most common real-world storage backend — it underpins every deployment that
reads or writes to disk — and it must therefore be the first production adapter
in the library.

The `LocalAdapter` must implement the full set of capability interfaces defined
in `src/Contract/` using only PHP's built-in I/O functions: no third-party
packages, no extensions beyond what ships with every standard PHP 8.0
installation. Critically, the adapter operates under a **configured root
directory** and must never allow callers to escape that root — path traversal
is a primary security concern for any filesystem adapter.

---

## Decision Drivers

- **Zero new Composer dependencies** — use only PHP built-in functions and the
  `fileinfo` extension (bundled with every PHP build since 5.3).
- **Security-first path resolution** — all virtual `Path` values must be
  resolved to an absolute OS path that is verified to remain within the
  configured root before any I/O takes place.
- **Full interface coverage** — `LocalAdapter` implements all seven capability
  interfaces; it is the reference implementation for the full contract.
- **Stream-first** — inherited from ADR-0001; all content I/O uses `resource`
  streams.
- **Consistent exception semantics** — every error is communicated through the
  existing exception hierarchy (`FileNotFoundException`,
  `DirectoryNotFoundException`, `PermissionDeniedException`,
  `AdapterException`); no raw PHP warnings or `false` return values leak to
  callers.
- **PHP 8.0 minimum** — no 8.1+ language features.

---

## Decisions

### 1. Constructor: Typed Root Path

`LocalAdapter` receives a single constructor argument: a `string $root`
representing an absolute path on the local filesystem.

```php
$adapter = new LocalAdapter('/var/storage');
$fs = new Filesystem($adapter);
```

**Why `string` rather than `Path`?** The `Path` Value Object models a
*virtual* storage path (normalised, separator-agnostic, rooted at `/`). The
root directory is an *OS-level* absolute path whose separator and format depend
on the host. Wrapping it in `Path` would conflate two distinct concepts. The
constructor validates and normalises the root via `realpath()` at construction
time, failing loudly with `\InvalidArgumentException` if the root does not
exist or is not a directory.

```
constructor
  └─ realpath($root)        → false  →  throw \InvalidArgumentException
  └─ is_dir(...)            → false  →  throw \InvalidArgumentException
  └─ store as $this->root (no trailing slash)
```

---

### 2. Path Resolution and Traversal Prevention

Every `Path` VO received by a public method is resolved to an absolute OS path
via a private `resolve(Path $path): string` helper:

```
resolve($path)
  1. $candidate = $this->root . (string) $path          // e.g. /var/storage/uploads/avatar.jpg
  2. $real      = realpath($candidate)                   // resolves symlinks & ..
     - if $real === false (path does not exist):
         use $candidate directly for write/mkdir/new-file operations
         (realpath() fails on non-existent paths)
  3. Verify strncmp($real, $this->root, strlen($this->root)) === 0
     - mismatch → throw AdapterException("Path escapes storage root")
  4. return $real (or $candidate for non-existent target paths)
```

Because `Path` already normalises `..` sequences (ADR-0001 §3), a
double-normalisation is guaranteed. The `realpath()` check is the OS-level
safety net against symlink-based traversal.

For **write and create operations** where the target does not yet exist,
`realpath()` returns `false`. In those cases the resolved path is verified by
checking that the *parent directory* of the candidate path is contained within
the root, before any I/O occurs.

---

### 3. Implemented Interfaces

`LocalAdapter` implements all seven capability interfaces:

| Interface               | PHP functions used                                              |
|-------------------------|-----------------------------------------------------------------|
| `ReadableInterface`     | `fopen($path, 'r')`                                            |
| `WritableInterface`     | `fopen($path, 'w')`, `stream_copy_to_stream()`, `unlink()`    |
| `InspectableInterface`  | `file_exists()`, `is_file()`, `is_dir()`                      |
| `TransferableInterface` | `copy()`, `rename()` (PHP built-in)                           |
| `ListableInterface`     | `opendir()`, `readdir()`, `closedir()`                        |
| `DirectoryInterface`    | `mkdir($path, 0777, true)`, recursive `unlink` + `rmdir()`   |
| `StatableInterface`     | `stat()`, `finfo_file()` / `mime_content_type()`              |

---

### 4. ReadableInterface — `read(Path $path): resource`

```
1. $abs = resolve($path)
2. if !is_file($abs)  → throw FileNotFoundException
3. $stream = fopen($abs, 'r')
4. if $stream === false  → throw AdapterException (wraps last error)
5. return $stream   (caller is responsible for fclose)
```

The returned stream is an open file handle. The caller (typically `Filesystem`)
is responsible for closing it. This mirrors the InMemoryAdapter contract.

---

### 5. WritableInterface — `write` and `delete`

#### `write(Path $path, resource $stream): void`

```
1. $abs = resolve($path)   [non-existent path OK]
2. Ensure parent directory exists (mkdir recursive, 0777) or throw AdapterException
3. $dest = fopen($abs, 'w')
4. if $dest === false  → throw PermissionDeniedException or AdapterException
5. stream_copy_to_stream($stream, $dest)
6. fclose($dest)
```

The `Path` VO's `__toString()` produces a normalised virtual path; `resolve()`
prepends the root to produce the OS path.

`write()` is **overwrite-safe**: if the file already exists it is truncated and
rewritten (mode `'w'`). The `FileAlreadyExistsException` is intentionally not
thrown here — overwrite semantics are consistent with how filesystems work and
with the `InMemoryAdapter`. Callers that require no-overwrite semantics should
call `exists()` before `write()`.

#### `delete(Path $path): void`

```
1. $abs = resolve($path)
2. if !is_file($abs)  → throw FileNotFoundException
3. unlink($abs) or throw PermissionDeniedException / AdapterException
```

---

### 6. InspectableInterface — `exists`, `isFile`, `isDir`

All three methods delegate to PHP built-ins after resolving the path. Symlinks
are treated transparently: `is_file()` and `is_dir()` follow symlinks, which is
the expected POSIX semantics.

```php
public function exists(Path $path): bool  { return file_exists($this->resolve($path)); }
public function isFile(Path $path): bool  { return is_file($this->resolve($path)); }
public function isDir(Path $path): bool   { return is_dir($this->resolve($path)); }
```

These methods never throw — they return `false` when the path does not exist,
consistent with the `InspectableInterface` contract.

---

### 7. TransferableInterface — `copy`, `move`, `rename`

#### `copy(Path $source, Path $destination): void`

```
1. $srcAbs  = resolve($source);   if !is_file → throw FileNotFoundException
2. $destAbs = resolve($destination)
3. Ensure parent dir of $destAbs exists
4. copy($srcAbs, $destAbs) or throw AdapterException
```

#### `move(Path $source, Path $destination): void`

```
1. $srcAbs  = resolve($source);   if !is_file → throw FileNotFoundException
2. $destAbs = resolve($destination)
3. rename($srcAbs, $destAbs) or throw AdapterException
```

PHP's `rename()` is atomic on POSIX filesystems when source and destination
reside on the same mount point. Cross-device moves are not explicitly handled
in v0.1.

#### `rename(Path $source, Path $destination): void`

Delegates to `move()`. The distinction between *rename* (same-directory) and
*move* (cross-directory) is not enforced at the adapter level — both are
fulfilled by PHP's `rename()`.

---

### 8. ListableInterface — `listContents(Path $path): Traversable`

Returns an immediate (non-recursive) listing of the directory, yielding `Path`
objects for files and sub-directories, skipping `.` and `..`.

```
1. $abs = resolve($path); if !is_dir($abs) → throw DirectoryNotFoundException
2. $dh = opendir($abs);   if false → throw AdapterException
3. while ($entry = readdir($dh)) !== false:
      if $entry === '.' || $entry === '..'  → skip
      yield new Path((string)$path . '/' . $entry)
4. closedir($dh)
```

A `Generator` is returned — it satisfies the `\Traversable` return type and is
lazy, so large directories do not exhaust memory.

---

### 9. DirectoryInterface — `createDirectory` and `deleteDirectory`

#### `createDirectory(Path $path): void`

```
1. $abs = resolve($path)
2. if is_dir($abs)  → return (idempotent, no exception)
3. mkdir($abs, 0777, /* recursive */ true) or throw AdapterException
```

Recursive creation (`true` third argument) means intermediate directories are
created automatically, matching the InMemoryAdapter's implicit behaviour.

#### `deleteDirectory(Path $path): void`

```
1. $abs = resolve($path); if !is_dir($abs) → throw DirectoryNotFoundException
2. Recursively delete all contained files and sub-directories (depth-first)
3. rmdir($abs) or throw AdapterException
```

The recursive deletion is implemented via an internal `deleteRecursive(string
$abs): void` helper using `opendir`/`readdir`; it does **not** use
`shell_exec` or `system()` — only native PHP functions, consistent with the
zero-new-dependency constraint.

---

### 10. StatableInterface — `metadata(Path $path): FileMetadata`

```
1. $abs = resolve($path); if !is_file($abs) → throw FileNotFoundException
2. $stat = stat($abs);    if false → throw AdapterException
3. $size       = $stat['size']
4. $modifiedAt = $stat['mtime']
5. $mimeType   = (new \finfo(FILEINFO_MIME_TYPE))->file($abs) ?: null
6. return new FileMetadata($size, $modifiedAt, $mimeType)
```

`finfo` is a bundled PHP extension (enabled by default since PHP 5.3). It is
not a Composer dependency. If `finfo::file()` returns `false` (e.g. unreadable
file), `$mimeType` is `null` — consistent with the `FileMetadata` contract
(`null` means unknown, not an error).

---

### 11. Error Mapping — PHP Errors → Domain Exceptions

PHP filesystem functions communicate failure through `false` return values and
`E_WARNING` emissions. `LocalAdapter` suppresses warnings by using the
`@` operator **only inside dedicated error-mapping helpers**, never across broad
code blocks. The mapping is:

| Condition | Exception thrown |
|---|---|
| File does not exist (read/delete/stat) | `FileNotFoundException` |
| Directory does not exist (list/deleteDir) | `DirectoryNotFoundException` |
| `is_writable()` returns false | `PermissionDeniedException` |
| Any `fopen`/`copy`/`rename`/`mkdir` returns false | `AdapterException` (wraps `error_get_last()`) |
| Path escapes root | `AdapterException` |

`AdapterException` wraps the raw PHP error message as its `$previous` cause so
that the original low-level detail is never silently discarded.

---

### 12. Permission Mode

Directory creation uses mode `0777` and relies on the process `umask` to apply
the effective permissions. Hardcoding a more restrictive mode (e.g. `0755`)
would be opinionated and surprising to callers running under different umask
configurations. Callers who require specific permissions should set `umask()`
before using the adapter.

---

## Out of Scope for This ADR

| Deferred Concern | Notes |
|---|---|
| Cross-device `move` (different mount points) | Will require copy-then-delete fallback; deferred |
| Visibility / public URL generation | Deferred (general ADR TBD) |
| Recursive `listContents` | Only immediate children are listed; recursive traversal is a caller concern |
| Symlink creation / `lstat` | Symlinks are read-through transparently; creation is out of scope |
| File locking (`flock`) | No advisory lock semantics in v0.1 |
| Windows path support | Primary target is POSIX; backslash normalisation is handled by `Path`, but Windows drive letters and UNC paths are not validated |

---

## Consequences

### Positive

- The `LocalAdapter` completes the full implementation of every capability
  interface, making `Filesystem` fully operational against real disk I/O.
- Using only PHP built-ins keeps the zero-dependency promise of ADR-0001 §7.
- Centralised path resolution (`resolve()`) is a single, auditable security
  boundary — all traversal prevention lives in one place.
- `finfo`-based MIME detection is reliable and does not require file extension
  guessing.
- Lazy `Generator` listing prevents memory exhaustion on large directories.

### Negative / Trade-offs

- `realpath()` on non-existent paths returns `false`; the traversal check for
  write targets relies on verifying the *parent* directory, which is slightly
  weaker than a full real-path check. This is a known and documented limitation.
- `rename()` is not atomic across mount points; cross-device moves will silently
  succeed on some OS configurations and fail on others.
- The `@` error-suppression pattern, even scoped to helpers, makes static
  analysis harder; PHPStan rules must be configured to allow it selectively.
- Umask-dependent directory permissions may produce unexpected results on
  multi-user systems; this is documented but not enforced.

---

## References

- [PHP Manual — Filesystem Functions](https://www.php.net/manual/en/ref.filesystem.php)
- [PHP Manual — finfo::file](https://www.php.net/manual/en/finfo.file.php)
- [PHP Manual — realpath](https://www.php.net/manual/en/function.realpath.php)
- [PHP Manual — rename](https://www.php.net/manual/en/function.rename.php)
- [OWASP — Path Traversal](https://owasp.org/www-community/attacks/Path_Traversal)
- [ADR-0001: Core Architecture](0001-core-architecture.md)
