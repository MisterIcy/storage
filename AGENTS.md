# AGENTS — Developer & AI Agent Guide for mistericy/storage

This file contains machine-readable project conventions for AI coding agents
(GitHub Copilot, Claude, etc.) and human contributors who want a fast
orientation. See [CONTRIBUTING.md](CONTRIBUTING.md) for the full human-readable
guide.

---

## Project Identity

| Field        | Value                                      |
|--------------|--------------------------------------------|
| Package      | `mistericy/storage`                        |
| Namespace    | `MisterIcy\Storage\`  (PSR-4, `src/`)      |
| Test NS      | `Tests\MisterIcy\Storage\` (PSR-4, `tests/`) |
| PHP minimum  | 8.0 (no 8.1+ syntax in `src/` or `tests/`) |
| License      | MIT                                        |

---

## Repository Layout

```
src/
  Filesystem.php                  # Single consumer-facing facade
  Adapter/
    InMemoryAdapter.php
  Contract/
    AdapterInterface.php
    DirectoryInterface.php
    InspectableInterface.php
    ListableInterface.php
    ReadableInterface.php
    StatableInterface.php
    TransferableInterface.php
    WritableInterface.php
  Exception/
    AdapterException.php
    DirectoryNotFoundException.php
    FileAlreadyExistsException.php
    FileNotFoundException.php
    OperationNotSupportedException.php
    PermissionDeniedException.php
    StorageException.php
  ValueObject/
    AbstractPath.php
    FileMetadata.php
    Path.php
tests/
  FilesystemTest.php
  Adapter/
    InMemoryAdapterTest.php
  Exception/
    ExceptionHierarchyTest.php
  ValueObject/
    FileMetadataTest.php
    PathTest.php
docs/
  adr/                            # Architecture Decision Records
build/
  infection/                      # Mutation testing reports (git-ignored)
```

---

## Local Dev Setup

```bash
# Install dependencies
composer install

# Run all quality gates (must all be green before any PR)
./vendor/bin/phpunit                                   # unit tests
./vendor/bin/phpstan analyse                           # static analysis
./vendor/bin/php-cs-fixer fix --dry-run --diff        # coding standards (check)
./vendor/bin/php-cs-fixer fix                          # coding standards (fix)
./vendor/bin/infection                                 # mutation testing
```

---

## Quality Thresholds

| Tool       | Configuration file        | Threshold / Level        |
|------------|---------------------------|--------------------------|
| PHPUnit    | `phpunit.xml`             | All tests must pass      |
| PHPStan    | `phpstan.dist.neon`       | Level 9, zero errors     |
| CS Fixer   | `.php-cs-fixer.dist.php`  | Zero violations          |
| Infection  | `infection.json5`         | MSI ≥ 85 %, Covered ≥ 85 % |

---

## Code Conventions

- **PHP 8.0 only** — do not use enums, fibers, readonly properties, first-class
  callable syntax, never/intersection types, or any other 8.1+ feature.
- **Stream-first** — file content is always passed and returned as `resource`
  (stream), never as strings.
- **Value Objects over primitives** — use `Path` (or a subclass of
  `AbstractPath`) instead of bare strings for paths; use `FileMetadata` for
  file stat results.
- **Interface segregation** — each capability is a separate interface in
  `src/Contract/`. Adapters implement only the interfaces they support.
- **No optional dependencies in core** — `src/` must have zero `require`
  dependencies. Optional adapter dependencies belong only in `composer.json`
  `suggest`.

---

## Branch & Commit Conventions

### Branch naming

```
feat/<short-description>
fix/<short-description>
docs/<short-description>
chore/<short-description>
test/<short-description>
refactor/<short-description>
perf/<short-description>
```

### Commit messages (Conventional Commits)

```
<type>(<optional scope>): <description>
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `ci`

Scopes (examples): `inmemory`, `path`, `filesystem`, `exception`

Breaking changes: append `!` to type and add `BREAKING CHANGE:` footer.

---

## Adding a New Adapter

Follow the **RFC → ADR → PR** process:

1. **RFC** — Open a Feature Request issue; check the adapter proposal box.
2. **ADR** — Author `docs/adr/NNNN-<name>-adapter.md` (separate PR, `docs/`
   branch).
3. **Implementation** — `src/Adapter/<Name>Adapter.php` + `tests/Adapter/<Name>AdapterTest.php`
   (separate PR, `feat/` branch).

New adapters must implement the relevant `Contract/` interfaces and pass all
quality gates.

---

## CI Pipeline

Defined in `.github/workflows/ci.yml`. Jobs run on every push to `main` and on
every PR targeting `main`:

1. `validate` — `composer validate --strict`
2. `coding-standards` — CS Fixer dry-run
3. `static-analysis` — PHPStan level 9
4. `tests` — PHPUnit across PHP 8.0 / 8.1 / 8.2 / 8.3
5. `mutation-testing` — Infection (MSI ≥ 85 %)

All jobs must be green for a PR to be mergeable.

---

## Security Reporting

Do **not** create public issues for security vulnerabilities. Use
[GitHub private Security Advisories](https://github.com/mistericy/storage/security/advisories/new).
