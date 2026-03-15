---
agent: agent
description: >
  Write PHPUnit tests for new or modified production code, targeting 100 %
  line/branch coverage on the changed file(s), then verify coverage and run
  Infection to maximise the mutation score.
---

# Create Tests for ${input:targetFile}

You are writing tests for `${input:targetFile}` in the `mistericy/storage`
library. Follow every rule below exactly. Make **only** the minimum changes
needed; do not modify production code unless you find a genuine bug.

---

## Step 0 — Read before you write

1. Read the production file at `${input:targetFile}` in full.
2. Read the corresponding test file if it already exists.
3. Identify every public method and every non-trivial branch (if/else, match,
   throw, loop) in the production file. These are your coverage targets.

---

## Step 1 — Locate or create the test file

- Mirror the `src/` path under `tests/`.  
  Example: `src/ValueObject/Path.php` → `tests/ValueObject/PathTest.php`
- Namespace: `Tests\MisterIcy\Storage\<sub-namespace>`
- Class name: `<ClassName>Test extends \PHPUnit\Framework\TestCase`
- File header:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\MisterIcy\Storage\<SubNamespace>;

  use PHPUnit\Framework\TestCase;
  ```

---

## Step 2 — Write test methods

### Naming

```php
public function test<MethodName><Scenario>(): void
```

Examples: `testWriteCreatesFile`, `testReadThrowsWhenFileNotFound`

### Coverage annotations

Every test method that exercises specific production code **must** carry a
`@covers` annotation referencing the exact class and method:

```php
/**
 * @covers \MisterIcy\Storage\ValueObject\Path::__construct
 * @covers \MisterIcy\Storage\ValueObject\Path::toString
 */
public function testToStringReturnsNormalisedPath(): void { ... }
```

The `phpunit.xml` configuration sets `forceCoversAnnotation="true"`, so a test
without `@covers` will be flagged as risky.

### What to test

For each public method write at minimum:
- **Happy path** — valid input, expected output.
- **Each exception branch** — use `$this->expectException()` /
  `$this->expectExceptionMessage()`.
- **Edge cases** — empty streams, root paths, boundary values.

### Streams

File content is always a `resource`. Create streams in tests with:

```php
/** @var resource $stream */
$stream = fopen('php://memory', 'r+');
fwrite($stream, 'content');
rewind($stream);
```

Always `fclose` resources in `tearDown` or after assertion to avoid leaks.

### Value objects

Use `Path` for paths, never bare strings:

```php
$path = new \MisterIcy\Storage\ValueObject\Path('some/file.txt');
```

---

## Step 3 — Run the tests and check coverage

```bash
./vendor/bin/phpunit
```

All tests must be green. Then verify coverage:

```bash
./vendor/bin/phpunit --coverage-text
```

Inspect the output for the file under test. Target: **100 % Lines** and
**100 % Branches** on new code. If coverage is below 100 %:
- Identify which line/branch is not covered.
- Add a focused test case that hits it.
- Re-run until coverage reaches 100 % (or document the excluded path with a
  clear reason, e.g. "requires real filesystem — integration test only").

---

## Step 4 — Run Infection to maximise mutation score

```bash
./vendor/bin/infection --filter=${input:targetFile}
```

Review the output. For each **escaped mutant**:

1. Identify what the mutant changed (the diff in the Infection output or
   `build/infection/infection.json`).
2. Add a focused assertion that only passes when the original logic is correct.
3. Re-run Infection to confirm the mutant is now killed.

**Thresholds that must be met (from `infection.json5`):**

| Metric | Minimum |
|--------|---------|
| MSI | 85 % |
| Covered MSI | 85 % |

Do **not** use `@infection-ignore-mutant` unless the mutant is semantically
equivalent — and if you do, add a comment explaining why.

---

## Step 5 — Run the full CI suite

```bash
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpstan analyse
./vendor/bin/phpunit
./vendor/bin/infection
```

All four gates must pass with zero errors before the task is complete.

---

## Common pitfalls

| Pitfall | Fix |
|---------|-----|
| `@covers` missing → risky test | Add the annotation to every test method |
| Using raw strings for paths | Use `new Path(...)` |
| Returning/accepting string file content | Use `resource` streams |
| Using 8.1+ syntax (enums, readonly, etc.) | Revert to PHP 8.0 syntax |
| Forgetting to `rewind()` a stream before reading | Always `rewind()` after writing |
| `fclose()` inside a loop | Assign stream to a property and close in `tearDown` |
