# Contributing to mistericy/storage

Thank you for considering a contribution to **mistericy/storage**. This
document explains how the project is developed, what the contribution workflow
looks like, and what quality gates every change must pass before it can be
merged.

---

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Workflow](#development-workflow)
4. [Branch Naming](#branch-naming)
5. [Commit Style](#commit-style)
6. [Code Quality Gates](#code-quality-gates)
7. [Proposing and Shipping a New Adapter](#proposing-and-shipping-a-new-adapter)
8. [Opening a Pull Request](#opening-a-pull-request)
9. [Review Process](#review-process)

---

## Code of Conduct

All participants are expected to uphold the [Code of Conduct](CODE_OF_CONDUCT.md).
Violations can be reported to
[icyd3mon+storage@gmail.com](mailto:icyd3mon+storage@gmail.com).

---

## Getting Started

### Prerequisites

| Tool     | Minimum version |
|----------|-----------------|
| PHP      | 8.0             |
| Composer | 2.x             |

### Fork and clone

```bash
# 1. Fork the repository on GitHub, then clone your fork
git clone https://github.com/<your-username>/storage.git
cd storage

# 2. Add the upstream remote so you can sync later
git remote add upstream https://github.com/mistericy/storage.git

# 3. Install all dependencies
composer install
```

### Running the full quality suite locally

```bash
# Unit tests
./vendor/bin/phpunit

# Static analysis (PHPStan level 9)
./vendor/bin/phpstan analyse

# Coding standards — check only (no changes)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Coding standards — auto-fix
./vendor/bin/php-cs-fixer fix

# Mutation testing (MSI baseline ≥ 85 %)
./vendor/bin/infection
```

All five checks must be green before you open a PR. The CI pipeline runs them
in the same order and will fail the build if any check does not pass.

---

## Development Workflow

This project uses a **Fork-and-PR** model. Trusted contributors do not push
feature branches directly to the upstream repository.

1. **Sync your fork** with upstream before starting any new work:

   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create a feature branch** (see [Branch Naming](#branch-naming) below).

3. **Make your changes**, writing or updating tests as you go.

4. **Run the full quality suite** locally and resolve any failures.

5. **Push to your fork** and open a Pull Request against
   `mistericy/storage:main`.

> **Important:** Never commit directly to `main`. That branch is protected and
> only accepts changes through reviewed and approved pull requests.

---

## Branch Naming

Use the following prefixes to make the purpose of a branch immediately clear:

| Prefix       | When to use                                          |
|--------------|------------------------------------------------------|
| `feat/`      | New feature or new adapter                           |
| `fix/`       | Bug fix                                              |
| `docs/`      | Documentation-only changes                          |
| `chore/`     | Build tooling, CI configuration, dependency updates |
| `test/`      | Tests only (no production code change)               |
| `refactor/`  | Code restructuring without behaviour change          |
| `perf/`      | Performance improvement                              |

**Examples:**
- `feat/local-filesystem-adapter`
- `fix/inmemory-seek-offset`
- `docs/adapter-proposal-process`
- `chore/upgrade-phpunit-9.6.35`

---

## Commit Style

This project follows the [Conventional Commits](https://www.conventionalcommits.org/)
specification. Every commit message must take the form:

```
<type>(<optional scope>): <description>

[optional body]

[optional footer(s)]
```

**Allowed types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`,
`chore`, `perf`, `ci`

**Scope** (optional): the component affected, e.g. `inmemory`, `path`,
`filesystem`, `exception`

### Examples

```
feat(inmemory): add support for recursive directory listing

fix(path): normalise trailing slashes on Windows paths

docs: add local dev setup to README

chore(deps): upgrade phpunit to 9.6.35

test(inmemory): add mutation coverage for write edge cases
```

### Breaking changes

Breaking changes must be flagged with a `!` after the type/scope and a
`BREAKING CHANGE:` footer:

```
feat(filesystem)!: remove deprecated overload of read()

BREAKING CHANGE: The second positional argument to read() has been removed.
Use the named argument $flags instead.
```

---

## Code Quality Gates

Every contribution **must** satisfy all of the following before a review is
requested:

| Gate              | Command                                                    | Requirement         |
|-------------------|------------------------------------------------------------|---------------------|
| Unit tests        | `./vendor/bin/phpunit`                                     | All tests pass      |
| Static analysis   | `./vendor/bin/phpstan analyse`                             | Zero errors (level 9) |
| Coding standards  | `./vendor/bin/php-cs-fixer fix --dry-run --diff`           | Zero violations     |
| Mutation testing  | `./vendor/bin/infection`                                   | MSI ≥ 85 %          |
| CI pipeline       | GitHub Actions                                             | All jobs green      |

New production code **must** be covered by tests. Pull requests that lower the
mutation score or leave new code uncovered will not be merged.

---

## Proposing and Shipping a New Adapter

New adapters are first-class citizens of this library. Because a new adapter
can introduce optional production dependencies and significant API surface, the
process is intentionally structured into three stages:

### Stage 1 — RFC (Request for Comments)

Open a **Feature Request** issue using the
[Feature Request template](.github/ISSUE_TEMPLATE/feature_request.yml) and
check the *"Is this a new adapter proposal?"* checkbox.

Your RFC must address:

- The **backend** being targeted (e.g. Google Cloud Storage, SFTP, Redis).
- The optional Composer package(s) that would become a new `suggest` entry in
  `composer.json`.
- Which **capability interfaces** the adapter will implement
  (`ReadableInterface`, `WritableInterface`, `ListableInterface`, etc.).
- A rough **API sketch** — how consumers will construct and configure the
  adapter.
- Any **backwards-compatibility considerations**.

The RFC stays open for community discussion for at least **7 days** before a
decision is made by the maintainer.

### Stage 2 — ADR (Architecture Decision Record)

Once the RFC reaches rough consensus, a maintainer (or you, in coordination
with a maintainer) will author an ADR in `docs/adr/` following the format
established in [`docs/adr/0001-core-architecture.md`](docs/adr/0001-core-architecture.md).

The ADR records:

- The decision and its rationale.
- Alternatives that were considered and why they were not chosen.
- Consequences and trade-offs.

The ADR is merged via a focused, standalone PR (branch prefix `docs/`) that is
separate from the implementation work.

### Stage 3 — Implementation PR

With the ADR accepted, open an implementation PR on a `feat/<name>-adapter`
branch that:

- Adds the adapter class under `src/Adapter/`.
- Adds a corresponding test class under `tests/Adapter/`.
- Updates the `suggest` section in `composer.json` if new optional packages
  are required.
- References the ADR number in the PR description.

The adapter must pass all quality gates before merge.

---

## Opening a Pull Request

- The [Pull Request template](.github/PULL_REQUEST_TEMPLATE.md) is applied
  automatically — fill in every section.
- If your work is not ready for review, open it as a **Draft PR** so reviewers
  know not to start yet.
- Link all related issues using GitHub keywords (`Closes #n`, `Relates to #n`).
- Keep PRs focused — one concern per PR. Stacked PRs are welcome.

---

## Review Process

- A maintainer will review within a reasonable timeframe.
- Address all review comments and push follow-up commits to the same branch.
- Request a re-review once all comments are resolved.
- Once approved and CI is green, a maintainer will merge using
  **squash-and-merge** to keep the history clean and linear.
