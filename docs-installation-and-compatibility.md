# Installation and Compatibility Notes

## Installation modes

### Recommended for local development

Use a Composer path repository from the BookStack host project:

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
  ],
  "require": {
    "kugarocks/bookstack-content-sync": "*@dev"
  }
}
```

Then run:

```bash
composer update kugarocks/bookstack-content-sync --no-interaction
```

## Why `*@dev` is required

The host BookStack application uses `minimum-stability: stable`.
While this package is still consumed as a local development package resolved to `dev-main`, the host requirement must allow that development version.

## What has been verified

The following has been verified in a local BookStack host:

- package discovery succeeds
- the package provider registers correctly
- the package owns the `bookstack:pull-content` command
- the package owns the `bookstack:push-content` command
- pull execution works against a local BookStack instance
- push plan execution works against pulled local content
- no-change `push --execute` execution completes successfully
- controlled write-path `push --execute` validation completes successfully in a minimal single-page update scenario

## What is not yet verified

- published package distribution outside local path repository usage
- automated cross-version compatibility checks

## Compatibility assumptions

This package is not designed as a standalone Laravel package.
It currently assumes:

- a BookStack host application
- Laravel 12 era dependencies
- availability of BookStack internal runtime services
- compatibility with the BookStack command registration lifecycle

## Operational caution

`bookstack:push-content --execute` can perform real remote write operations.
Use a controlled environment and review the plan output before running execute.
