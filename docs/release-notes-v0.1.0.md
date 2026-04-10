# Release Notes: v0.1.0

## Summary

`v0.1.0` is the first public packaged release of `kugarocks/bookstack-content-sync`.
It provides BookStack content sync as an external Composer package that can be installed into a BookStack host application while keeping the sync implementation aligned with the BookStack runtime model.

## What is included

- package-provided `bookstack:pull-content`
- package-provided `bookstack:push-content`
- migrated pull and push sync implementation
- Laravel package discovery integration for a BookStack host
- standalone package test suite for migrated sync logic

## Verification completed

This release has been verified with the following checks:

- Composer path repository installation in a local BookStack host
- package discovery and command ownership takeover
- `bookstack:pull-content --help`
- `bookstack:push-content --help`
- successful pull execution
- successful push plan execution
- successful no-change `push --execute` execution
- successful controlled write-path `push --execute` execution using a minimal single-page update, followed by restoration to a no-change state
- package test suite passing with `93 tests` and `364 assertions`

## Notes for adopters

- This package is intentionally coupled to a BookStack host runtime.
- It should not be treated as a generic standalone Laravel package.
- Compatibility has been verified in a local host workflow, not across a broad BookStack version matrix.
- Real remote write-path validation remains a controlled manual verification step.

## Packaging

- The repository keeps `todo/` for internal planning and execution records.
- `todo/` is excluded from release archives via `.gitattributes` export rules.

## Suggested short release text

First public packaged release of external BookStack content sync commands.
Includes Composer-installable pull and push workflows, host-integrated verification, migrated tests, and a controlled real write-path push execute check.
