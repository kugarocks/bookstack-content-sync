# Release Notes: v0.1.0

## Overview

`v0.1.0` is the first packaged release candidate for `kugarocks/bookstack-content-sync`.
It provides BookStack content sync as an external Composer package that is installed into a BookStack host application, without extracting the sync implementation away from the BookStack runtime model.

## Included capabilities

- `bookstack:pull-content` command provided by the package
- `bookstack:push-content` command provided by the package
- pull and push sync logic migrated into the package
- package service provider integration through Laravel package discovery
- standalone package test suite for migrated sync logic

## Verification completed

The following has been verified during local development:

- Composer path repository installation in a local BookStack host
- package discovery and command ownership takeover
- `bookstack:pull-content --help`
- `bookstack:push-content --help`
- successful pull execution
- successful push plan execution
- successful no-change `push --execute` execution
- successful controlled write-path `push --execute` execution using a minimal single-page update, followed by restoration to a no-change state
- package test suite passing with `93 tests` and `364 assertions`

## Known limitations

- This package is intentionally coupled to a BookStack host runtime.
- It should not be treated as a generic standalone Laravel package.
- Compatibility has been validated in a local host workflow, not across a broad BookStack version matrix.
- Real remote write-path validation is still a controlled manual process, not an automated release check.

## Packaging notes

- The repository keeps `todo/` for internal planning and execution records.
- `todo/` is excluded from release archives via `.gitattributes` export rules.

## Recommended release message basis

Suggested short release summary:

- First packaged release of external BookStack content sync commands
- Pull and push workflows migrated from the source branch into a Composer-installable package
- Host-integrated verification completed, including a controlled real write-path push execute check
