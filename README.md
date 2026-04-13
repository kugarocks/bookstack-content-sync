# bookstack-content-sync

BookStack content sync workflow for pulling content into a local directory, making local changes, and pushing those changes back to BookStack.

## Current status

This package currently provides a working host-integrated implementation for:

- `bookstack:init-content-dir`
- `bookstack:pull-content`
- `bookstack:push-content`

Verified in a local BookStack host environment:

- package installation via Composer path repository
- Laravel package discovery
- command ownership takeover for pull and push
- successful pull execution
- successful push plan execution
- successful no-change push execute execution
- successful controlled write-path push execute execution with post-check stabilization

`push --execute` has been verified in both a controlled no-change scenario and a minimal real write-path scenario in the local BookStack host workflow.

## Requirements

- PHP 8.2+
- A BookStack host application running Laravel 12
- A host environment that provides BookStack internal services such as `BookStack\Http\HttpRequestService`

## Installation

### Packagist installation

Once the package is available on Packagist, install it in the BookStack host with:

```bash
composer require kugarocks/bookstack-content-sync:^0.1
```

If you want to pin exactly the first public release instead:

```bash
composer require kugarocks/bookstack-content-sync:0.1.0
```

### Local development installation

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

## Commands

### Initialize content

Create a local content directory and starter `sync.json`:

```bash
php artisan bookstack:init-content-dir /path/to/content
```

This command creates the target directory if needed, writes `sync.json`, and reminds you which environment variables to export before running a pull.

### Pull

```bash
php artisan bookstack:pull-content /path/to/content
```

### Push plan

```bash
php artisan bookstack:push-content /path/to/content
```

### Push execute

```bash
php artisan bookstack:push-content /path/to/content --execute
```

## Compatibility notes

This package is intentionally coupled to a BookStack host runtime.

Current assumptions:

- the host already contains Laravel framework dependencies
- the host provides BookStack internal services used by the sync implementation
- command names can be overridden by the package provider during registration

Because of that, compatibility should be treated as BookStack-version-sensitive rather than generic-Laravel-package-compatible.

## Verification summary

Local verification completed so far:

- install by path repository in the BookStack `slug` branch host
- `bookstack:pull-content --help`
- command ownership check for pull
- successful minimal pull run to `/tmp/bookstack-content-sync-smoke`
- `bookstack:push-content --help`
- command ownership check for push
- successful push plan run with `No remote changes required`

## Running tests

Install dependencies and run the full package test suite:

```bash
composer install
composer test
```

Test boundaries:

- Unit tests validate isolated sync logic.
- Integration tests execute real local file reads and writes in temporary directories.
- Integration tests do not call a real BookStack server; HTTP is mocked through the package test shim.
- Host verification is still required to confirm package discovery and real command takeover inside BookStack.

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

You can still run PHPUnit directly if needed:

```bash
vendor/bin/phpunit tests
```

## Next steps

- decide whether the host-side verification setup should be preserved as-is or cleaned up before release
- tighten package dependency declarations as needed after more host validation
- publish the first public tag when ready

## Release prep

Before tagging or publishing a version, use `docs/release/guide.md` for the release checklist, command order, and versioning guidance.

For the current first-release draft text, see `docs/release/notes-v0.1.0.md`.
