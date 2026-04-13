# Release Notes: v0.1.6

## Summary

`v0.1.6` fixes host-version detection for `kugarocks/bookstack-content-sync` when BookStack is installed from a local development branch.
The package now prefers BookStack's own application version sources before falling back to Composer's root package version.

## What is included

- host-version detection via `BookStack\App\AppVersion::get()` when available
- fallback host-version detection via the BookStack root `version` file
- Composer root package version used only as a final fallback
- unit tests covering detection priority across app version, version file, and Composer fallback
- compatibility preserved for the existing minimum supported BookStack version of `26.03`

## Verification completed

This release has been verified with the following checks:

- `php vendor/bin/phpunit tests/Unit/Support/BookStack/HostVersionGuardTest.php`

## Notes for adopters

- This release fixes false negatives where the host project runs BookStack `26.03+` but Composer reports a branch-style root version such as `dev-sync`.
- The runtime compatibility guard still requires BookStack `26.03+`.
- Release-version detection is now aligned more closely with how BookStack itself exposes its application version.

## Suggested short release text

Fix host-version detection for local BookStack branch installs by preferring BookStack's application version over Composer's branch-style root version.
