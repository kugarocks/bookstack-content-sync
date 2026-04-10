# Release Notes: v0.1.2

## Summary

`v0.1.2` is a small usability release for `kugarocks/bookstack-content-sync`.
It improves the first-run workflow for content directory setup, aligns the initializer command name with the repository wording, and cleans up the README language around content usage.

## What is included

- initializer command for creating a local sync directory and starter `sync.json`
- initializer command name finalized as `bookstack:init-content-dir`
- improved `bookstack:pull-content` guidance when `sync.json` is missing
- README command examples updated to consistently use `content` wording

## Verification completed

This release has been verified with the following checks:

- `composer dump-autoload`
- `php vendor/bin/phpunit tests/Integration/ContentSync/InitContentProjectCommandIntegrationTest.php tests/Integration/ContentSync/PullContentCommandIntegrationTest.php`

## Notes for adopters

- For a new local content directory, run `php artisan bookstack:init-content-dir /path/to/content` before the first pull.
- If `sync.json` is missing, the pull command now points you to the initializer command directly.
- No pull or push sync behavior changes are intended in this release; the changes are focused on setup ergonomics and documentation clarity.

## Suggested short release text

Usability release that adds a clearer content directory initializer workflow, standardizes the command name as `bookstack:init-content-dir`, and improves pull guidance and README examples.
