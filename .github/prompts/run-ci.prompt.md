---
agent: agent
description: >
  Execute all four CI quality gates locally (PHP CS Fixer, PHPStan, PHPUnit,
  Infection) in the correct order, fix every violation found, and confirm
  all gates are green before finishing.
---

# Run CI Quality Gates

Execute all CI checks **in the order below**. Fix every failure before moving
to the next gate. Do not skip or reorder steps — later tools depend on earlier
ones being green.

---

## Gate 1 — Coding Standards (PHP CS Fixer)

```bash
./vendor/bin/php-cs-fixer fix
```

- This applies fixes **in-place**. Do **not** use `--dry-run`.
- After running, check for modified files:
  ```bash
  git diff --name-only
  ```
- All formatting violations are auto-corrected by the tool. If the command
  exits non-zero for a reason other than applying fixes, read the error
  output and resolve the root cause before continuing.

**Expected result:** exit code 0 with "No changes made" or "Fixed N file(s)".

---

## Gate 2 — Static Analysis (PHPStan level 9)

```bash
./vendor/bin/phpstan analyse
```

- Level 9 is configured in `phpstan.dist.neon`. Do not lower the level.
- Analyse both `src/` and `tests/`.
- For each reported error:
  1. Read the offending file and line.
  2. Fix the type error, missing return type, undefined method, etc.
  3. Re-run PHPStan after each fix to confirm it is resolved.
- Do **not** add `@phpstan-ignore` suppressions without a comment explaining
  the false positive and why it cannot be fixed at the source.

**Expected result:** "No errors" with exit code 0.

---

## Gate 3 — Unit Tests (PHPUnit)

```bash
./vendor/bin/phpunit
```

- All tests must pass.
- For every failing test:
  1. Read the failure message and stack trace.
  2. Determine whether it is a test bug or a production bug.
  3. Fix the root cause — do not delete or skip tests.
- After all tests pass, verify line and branch coverage:
  ```bash
  ./vendor/bin/phpunit --coverage-text
  ```
  New code must have **100 % line and branch coverage** (except integration-
  heavy paths — document any exclusions with a clear comment).

**Expected result:** all tests green, exit code 0.

---

## Gate 4 — Mutation Testing (Infection)

```bash
./vendor/bin/infection
```

Thresholds enforced by `infection.json5`:

| Metric | Minimum |
|--------|---------|
| MSI (Mutation Score Indicator) | 85 % |
| Covered MSI | 85 % |

If the run fails due to escaped mutants:

1. Open `build/infection/infection.json` (machine-readable) or
   `build/infection/infection.html` (visual) to inspect escaped mutant diffs.
2. For each escaped mutant, add a focused assertion in the relevant test file
   that only passes with the original logic.
3. Re-run Infection:
   ```bash
   # Targeted re-run (faster)
   ./vendor/bin/infection --filter=src/Path/To/ChangedFile.php

   # Full re-run
   ./vendor/bin/infection
   ```
4. Repeat until both MSI and Covered MSI are ≥ 85 %.

Do **not** suppress mutants with `@infection-ignore-mutant` unless they are
provably semantically equivalent. If you must suppress one, add a comment:

```php
/** @infection-ignore-mutant IncrementInteger — return value is never read by callers */
```

**Expected result:** MSI ≥ 85 %, Covered MSI ≥ 85 %, exit code 0.

---

## Final verification

After all four gates pass, run the full sequence once more in one shot to
confirm no gate regressed as a side-effect of fixes made in another:

```bash
./vendor/bin/php-cs-fixer fix && \
./vendor/bin/phpstan analyse && \
./vendor/bin/phpunit && \
./vendor/bin/infection
```

All commands must exit with code 0. Only then is the CI run complete.

---

## Quick-reference: what each gate checks

| Gate | Tool | Config file | Threshold |
|------|------|-------------|-----------|
| Coding standards | php-cs-fixer | `.php-cs-fixer.dist.php` | Zero violations |
| Static analysis | PHPStan | `phpstan.dist.neon` | Level 9, zero errors |
| Unit tests | PHPUnit | `phpunit.xml` | All tests green |
| Mutation testing | Infection | `infection.json5` | MSI ≥ 85 %, Covered ≥ 85 % |
