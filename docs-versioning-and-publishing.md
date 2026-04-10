# Versioning and Publishing Guide

## Recommended first release

Recommended first public tag:

- `v0.1.0`

Why `0.1.0` instead of `1.0.0`:

- The package is already functional and tested.
- The package is still intentionally coupled to a BookStack host runtime.
- Compatibility has been validated in a local host workflow, but not across a broad BookStack version matrix.
- A `0.x` series better reflects that the integration surface may still need adjustment.

## Versioning approach

Use semantic versioning with a `v` tag prefix.

Examples:

- `v0.1.0` for the first usable public release
- `v0.1.1` for small fixes and documentation-only compatibility-safe changes
- `v0.2.0` for new behavior or integration changes that remain pre-1.0
- `v1.0.0` only after the supported compatibility contract is intentionally defined and repeatedly validated

## Suggested release rules

### Patch releases

Use a patch release when:

- fixing bugs without changing the expected package integration model
- improving tests or documentation without changing runtime behavior
- tightening validation without changing normal successful flows

### Minor releases in `0.x`

Use a minor release when:

- changing command behavior in a way users need to notice
- changing configuration expectations
- changing assumptions about the host BookStack runtime
- adding meaningful new sync functionality

## Release note draft

Use `docs-release-notes-v0.1.0.md` as the starting point for the first public release text.

## Tagging example

Create and push a release tag:

```bash
git tag v0.1.0
git push origin v0.1.0
```

If you want an annotated tag:

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin v0.1.0
```

## Release artifact notes

- `todo/` is kept in the repository for internal planning and logs.
- `todo/` is excluded from release archives via `.gitattributes` export rules.

## Packagist readiness

Before publishing on Packagist, confirm the following:

- the repository is reachable by Packagist
- `composer.json` package name is correct: `kugarocks/bookstack-content-sync`
- `license` is present and correct
- PSR-4 autoload configuration matches the code namespace
- the default branch and tags are visible in the remote repository
- `README.md` explains that this package is for a BookStack host, not generic Laravel use
- the current compatibility notes do not promise more than has been verified

## Release checklist linkage

Use `docs-release-checklist.md` together with this guide:

- `docs-release-checklist.md` covers the release-time verification flow
- `docs-versioning-and-publishing.md` covers version naming and publishing expectations

## Post-release validation

After tagging or publishing, it is still recommended to verify:

- dependency resolution from a clean host install
- package discovery inside the BookStack host
- `php artisan bookstack:pull-content --help`
- `php artisan bookstack:push-content --help`
- at least one controlled pull or push workflow relevant to the release
