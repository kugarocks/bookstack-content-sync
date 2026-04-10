# bookstack-content-sync

External Composer extension for running BookStack content sync commands from a BookStack host application.

## Current status

This package currently provides a working host-integrated implementation for:

- `bookstack:pull-content`
- `bookstack:push-content`

Verified in a local BookStack host environment:

- package installation via Composer path repository
- Laravel package discovery
- command ownership takeover for pull and push
- successful pull execution
- successful push plan execution

`push --execute` has not been executed yet in this repository workflow. The current decision is that a controlled no-change execute run is safe to perform in a later round.

## Requirements

- PHP 8.2+
- A BookStack host application running Laravel 12
- A host environment that provides BookStack internal services such as `BookStack\Http\HttpRequestService`

## Installation

### Local path repository

Add the local repository to the BookStack host `composer.json`:

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

Require the package from the BookStack host:

```bash
composer require kugarocks/bookstack-content-sync:*@dev
```

The `@dev` suffix is needed for local path installation while the package is still resolved as `dev-main`.

## Commands

### Pull

```bash
php artisan bookstack:pull-content /path/to/project
```

### Push plan

```bash
php artisan bookstack:push-content /path/to/project
```

### Push execute

```bash
php artisan bookstack:push-content /path/to/project --execute
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

## Next steps

- run a controlled no-change `push --execute` validation
- tighten package dependency declarations as needed after more host validation
- add automated verification coverage where practical
