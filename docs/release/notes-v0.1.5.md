# Release Notes: v0.1.5

## Summary

`v0.1.5` adds an explicit host-version guard for `kugarocks/bookstack-content-sync`.
The package now refuses to load unless the detected BookStack host version is `26.03` or newer.

## What is included

- runtime BookStack host-version validation during service provider registration
- rejection of unsupported hosts below BookStack `26.03`
- rejection of hosts whose BookStack root package version cannot be determined as a release version
- unit tests covering supported, unsupported, and undetectable host version cases
- updated compatibility documentation to state the minimum supported BookStack version clearly

## Verification completed

This release has been verified with the following checks:

- `php vendor/bin/phpunit tests/Unit/Support/BookStack/HostVersionGuardTest.php`
- `php vendor/bin/phpunit tests/Integration/ContentSync/InitContentProjectCommandIntegrationTest.php`
- `php vendor/bin/phpunit tests/Unit`

## Notes for adopters

- This package now requires BookStack `26.03+`.
- If the host project version resolves to a non-release value such as `dev-main`, package registration will fail until the host version is exposed as a comparable release version.
- This is a runtime compatibility guard, not just a documentation-only requirement.

## Suggested short release text

Compatibility hardening release that enforces a minimum supported BookStack host version of `26.03` during package registration.
