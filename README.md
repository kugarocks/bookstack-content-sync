# bookstack-content-sync

Pulling BookStack content into a local directory, making changes, and pushing it back.

## Requirements

- PHP `>=8.2`
- BookStack `>=26.03`

## Slug Behavior

- Official BookStack does not support custom slug preservation for content entities when they are created or updated through the API.
- A host that includes [the BookStack custom slug support change](https://github.com/kugarocks/BookStack/commit/e6c75b4d13dab676424461c210b14f730c2a6ad3) adds custom slug support for those content entity APIs.
- When the host still does not preserve the requested slug, `bookstack:push-content --execute` treats the remote slug as the source of truth.
- The command prints a warning, then rewrites the local file slug and `snapshot.json` slug to the remote value returned by BookStack.

## Installation

### Packagist

```bash
composer require kugarocks/bookstack-content-sync
```

### Local Development

For local development against an unpublished working tree, add the local repository to the BookStack host `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../bookstack-content-sync",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then require the package from the BookStack host:

```bash
composer require kugarocks/bookstack-content-sync:*@dev
```

The `@dev` suffix is only needed for local path installation while the package is resolved as `dev-main`.

## Quick Start

### Initialize Directory

Create a local content directory and starter `sync.json`:

```bash
php artisan bookstack:init-content-dir /path/to/content
```

This command creates the target directory if needed, writes `sync.json`, and reminds you which environment variables to export before running a pull.

### Pull Content

```bash
php artisan bookstack:pull-content /path/to/content
```

### Push Plan

```bash
php artisan bookstack:push-content /path/to/content
```

### Push Execution

```bash
php artisan bookstack:push-content /path/to/content --execute
```

## Testing

Install dependencies and run the full package test suite:

```bash
composer install
composer test
```

Test boundaries:

- Unit tests validate isolated sync logic.
- Integration tests execute real local file reads and writes in temporary directories.
- Integration tests do not call a real BookStack server; HTTP is mocked through the package test shim.

Run only unit tests:

```bash
composer test-unit
```

Run only integration tests:

```bash
composer test-integration
```

Run pull-focused tests:

```bash
composer test-pull
```

Run push-focused tests:

```bash
composer test-push
```

