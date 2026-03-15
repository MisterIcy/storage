# mistericy/storage

**Storage & File System Abstraction Library for PHP 8.0+**

[![CI](https://github.com/mistericy/storage/actions/workflows/ci.yml/badge.svg)](https://github.com/mistericy/storage/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

`mistericy/storage` provides a unified, stream-first storage abstraction over
multiple backends. Swap between local disk, in-memory, AWS S3, FTP, and custom
adapters by injecting a different adapter — no other code changes required.

---

## Features

- **Zero mandatory dependencies** — the core library has no required
  third-party dependencies.
- **Stream-first** — all file content is exchanged as PHP `resource` streams.
- **Interface-segregated** — consumers type-hint only the capabilities they
  need (`ReadableInterface`, `WritableInterface`, `ListableInterface`, etc.).
- **PHP 8.0 compatible** — works with legacy and modern codebases alike.
- **Trivially testable** — swap in `InMemoryAdapter` during tests, no mocks
  required.

---

## Installation

```bash
composer require mistericy/storage
```

Optional backend packages (install only what you need):

```bash
# AWS S3 support
composer require aws/aws-sdk-php

# PSR-11 container integration
composer require psr/container

# PSR-3 logging support
composer require psr/log
```

---

## Quick Start

```php
use MisterIcy\Storage\Filesystem;
use MisterIcy\Storage\Adapter\InMemoryAdapter;
use MisterIcy\Storage\ValueObject\Path;

$fs = new Filesystem(new InMemoryAdapter());

// Write
$stream = fopen('php://temp', 'r+');
fwrite($stream, 'Hello, world!');
rewind($stream);
$fs->write(new Path('/greetings/hello.txt'), $stream);

// Read
$result = $fs->read(new Path('/greetings/hello.txt'));
echo stream_get_contents($result); // Hello, world!
```

---

## Local Development Setup

### Prerequisites

| Tool     | Minimum version |
|----------|-----------------|
| PHP      | 8.0             |
| Composer | 2.x             |

### Clone and install

```bash
git clone https://github.com/mistericy/storage.git
cd storage
composer install
```

### Run the test suite

```bash
# Unit tests
./vendor/bin/phpunit

# Static analysis (PHPStan level 9)
./vendor/bin/phpstan analyse

# Coding standards check (read-only)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix coding standards
./vendor/bin/php-cs-fixer fix

# Mutation testing (MSI baseline ≥ 85 %)
./vendor/bin/infection
```

Mutation reports are written to `build/infection/` (HTML, JSON, text, and
per-mutator Markdown).

---

## Architecture

The library is built around a **Strategy Pattern**:

```
Filesystem (facade)
    └── AdapterInterface (strategy)
            ├── InMemoryAdapter
            ├── LocalAdapter        (planned)
            ├── S3Adapter           (planned)
            └── FtpAdapter          (planned)
```

The full design rationale is documented in
[docs/adr/0001-core-architecture.md](docs/adr/0001-core-architecture.md).

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md)
before opening an issue or pull request. New adapter proposals follow the
**RFC → ADR → PR** process described there.

---

## Security

Please **do not** open a public issue for security vulnerabilities. Use
[GitHub's private Security Advisory](https://github.com/mistericy/storage/security/advisories/new)
instead.

---

## Code of Conduct

This project adheres to the [Contributor Covenant v2.1](CODE_OF_CONDUCT.md).
By participating, you agree to uphold its terms.

---

## License

MIT © 2026 [Alexandros Koutroulis](https://mistericy.github.io)
