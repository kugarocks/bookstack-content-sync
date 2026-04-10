# Release Notes: v0.1.1

## Summary

`v0.1.1` is a maintenance release focused on packaging and release workflow polish for `kugarocks/bookstack-content-sync`.
It keeps the pull and push command behavior unchanged while making Packagist publishing easier and removing a namespace collision risk when the package is installed into a BookStack host.

## What is included

- Packagist publishing helper at `packagist.sh`
- release helper at `release.sh`
- simplified Packagist helper defaults for the canonical repository and package name
- improved helper script terminal output and help text
- updated release documentation for the root-level helper scripts
- removal of package-provided `BookStack\Http\*` class exposure that could conflict with host application classes

## Verification completed

This release has been verified with the following checks:

- `bash packagist.sh help`
- `bash release.sh`
- `composer dump-autoload`
- `php vendor/bin/phpunit tests/Unit/ContentSync/Pull/BookStackApiClientTest.php tests/Integration/ContentSync/PullContentRunnerIntegrationTest.php tests/Integration/ContentSync/PushContentRunnerIntegrationTest.php`

## Notes for adopters

- This release is primarily operational polish and packaging safety; it does not introduce new end-user sync commands.
- The Packagist and release helper scripts now live at the repository root for easier direct execution.
- Internal helper classes now stay within the package namespace, so Composer should no longer warn about ambiguous resolution against BookStack host classes such as `BookStack\Http\HttpRequestService` and `BookStack\Http\HttpClientHistory`.

## Suggested short release text

Maintenance release that improves Packagist publishing and release workflow helpers, moves those scripts to the repository root, and removes BookStack namespace collisions during package installation.
