# Release Checklist

## Scope

Use this checklist before tagging or publishing a new version of `kugarocks/bookstack-content-sync`.

## Package validation

- Confirm the working tree is clean.
- Run `composer validate --strict`.
- Run `composer test`.
- Review `README.md` and `docs-installation-and-compatibility.md` for any stale compatibility claims.
- Confirm `todo/02-progress.md` reflects the latest verified state.

## Host validation

Verify the package in a local BookStack host before release.

Recommended host checks:

- Install via Composer path repository or VCS source.
- Confirm package discovery succeeds.
- Run `php artisan bookstack:pull-content --help`.
- Run `php artisan bookstack:push-content --help`.
- Confirm the package owns both command registrations.
- Perform at least one controlled pull run.
- If the release changes push behavior, perform a controlled `bookstack:push-content --execute` validation.

## Compatibility review

- Re-check the target BookStack branch or commit that was used for validation.
- Confirm any direct dependency on BookStack internal services is still valid.
- Record any known gaps where compatibility is assumed rather than verified.

## Versioning and publishing

- Choose the release version and create a matching Git tag.
- Ensure the repository metadata is ready for Packagist if publishing publicly.
- Add concise release notes describing new behavior, fixes, and any compatibility caveats.
- Publish only after the validation steps above are complete.

## Current known limitations

- The package is intentionally coupled to a BookStack host runtime.
- Package-level tests can be run locally, but they do not replace full host installation verification.
- Real remote write-path automation is still expected to be validated manually in a controlled environment.
