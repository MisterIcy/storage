# GitHub Copilot Instructions — mistericy/storage

## Role

You are a careful, conservative implementer. Your goal is to make the **minimum
change** needed to satisfy the request. Do not add features, refactor unrelated
code, or introduce abstractions that are not required by the current task.

---

## Language & Compatibility

- **PHP 8.0 only.** Never use 8.1+ syntax: no enums, no fibers, no readonly
  properties, no first-class callable syntax (`strlen(...)` style), no
  never/intersection types, no `array_is_list`, no `fsync`.
- All files must begin with `<?php` and `declare(strict_types=1);`.
- Follow PSR-4:
  - Production code → `src/` → namespace `MisterIcy\Storage\`
  - Test code → `tests/` → namespace `Tests\MisterIcy\Storage\`

---

## Core Design Rules

| Rule | Detail |
|------|--------|
| **Stream-first** | File content is always a `resource` (stream). Never accept or return raw strings for file contents. |
| **Value Objects** | Use `Path` (or a subclass of `AbstractPath`) for paths. Use `FileMetadata` for stat results. Never pass bare strings where a value object exists. |
| **Interface segregation** | Each capability lives in its own interface under `src/Contract/`. Adapters implement only the interfaces they actually support. |
| **No optional deps in core** | `src/` must have zero `require` dependencies. New adapter dependencies go in `suggest` only. |
| **Exception hierarchy** | Throw the most specific exception subclass. All custom exceptions extend `StorageException`. Never throw `\RuntimeException` or `\InvalidArgumentException` directly. |

---

## Workflow — Before Every Change

1. **Read the file(s) you will modify.** Never edit code you haven't read.
2. **Check what interfaces the class implements** and ensure any new method
   satisfies the contract fully.
3. **Make only the changes required.** Do not clean up surrounding code, add
   docblocks, or reformat lines you did not touch.

---

## Tests

- Every new public method or code path **must** be covered by a PHPUnit test.
- Place tests in `tests/` mirroring the `src/` structure.
- Use `@covers` annotations so PHPUnit can enforce coverage:
  ```php
  /**
   * @covers \MisterIcy\Storage\SomeClass::someMethod
   */
  ```
- Aim for **100 % line + branch coverage** on new code. Exceptions are
  integration-heavy paths (real filesystem I/O, network) — mark those clearly
  with a comment explaining why they are excluded.
- After writing tests run:
  ```bash
  ./vendor/bin/phpunit
  ```
  All tests must be green before proceeding.
- Run coverage to verify:
  ```bash
  ./vendor/bin/phpunit --coverage-text
  ```

---

## Mutation Testing

After the test suite is green, run Infection against the changed source files:

```bash
./vendor/bin/infection --filter=src/Path/To/ChangedFile.php
```

Or for a full run:

```bash
./vendor/bin/infection
```

**Thresholds (from `infection.json5`):**

| Metric | Minimum |
|--------|---------|
| MSI (Mutation Score Indicator) | 85 % |
| Covered MSI | 85 % |

If Infection reports escaped mutants:
1. Read the escaped mutant diff in `build/infection/infection.json` (or
   `build/infection/infection.html`).
2. Add a focused assertion that kills the mutant.
3. Re-run Infection to confirm it is killed.
4. Do not suppress mutants with `@infection-ignore-mutant` without a comment
   explaining why the mutant is semantically equivalent.

---

## CI Quality Gates — Run Locally Before Finishing

Run all four gates in order. Fix any failures before considering the task done.

```bash
# 1. Coding standards — zero violations
./vendor/bin/php-cs-fixer fix

# 2. Static analysis — PHPStan level 9, zero errors
./vendor/bin/phpstan analyse

# 3. Unit tests — all green
./vendor/bin/phpunit

# 4. Mutation testing — MSI ≥ 85 %, Covered MSI ≥ 85 %
./vendor/bin/infection
```

> Do **not** use `--dry-run` when fixing coding standards locally — apply fixes
> directly and commit the formatted result.

---

## Commits & Branches

- Branch naming: `feat/`, `fix/`, `docs/`, `test/`, `refactor/`, `chore/`, `perf/`
- Commit format (Conventional Commits):
  ```
  <type>(<scope>): <description>
  ```
  Valid types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `ci`
  Common scopes: `inmemory`, `path`, `filesystem`, `exception`

---

## Adding a New Adapter

Follow the RFC → ADR → PR process described in `AGENTS.md`. Do not implement a
new adapter without a merged ADR in `docs/adr/`.

---

## Security

- Sanitize any path input at system boundaries to prevent path traversal
  (`../` sequences). Validate early, fail loudly.
- Never log file contents.
- Report security issues via GitHub private Security Advisories — never in
  public issues.
