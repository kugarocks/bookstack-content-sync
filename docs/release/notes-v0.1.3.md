# Release Notes: v0.1.3

## Summary

`v0.1.3` is a small output polish release for `kugarocks/bookstack-content-sync`.
It keeps sync behavior unchanged and improves the console presentation of the pull and push commands so command output starts with a leading blank line.

## What is included

- leading blank line before `bookstack:pull-content` status output
- leading blank line before `bookstack:push-content` status output
- integration coverage for the adjusted command output formatting

## Verification completed

This release has been verified with the following checks:

- `php vendor/bin/phpunit tests/Integration/ContentSync/PullContentCommandIntegrationTest.php tests/Integration/ContentSync/PushContentCommandIntegrationTest.php`

## Notes for adopters

- No sync logic or command names changed in this release.
- This release only adjusts CLI output formatting for a cleaner first line in pull and push command output.

## Suggested short release text

Small polish release that adds a leading blank line to pull and push command output for cleaner CLI presentation.
