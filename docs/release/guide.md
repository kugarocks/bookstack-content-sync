# Releasing

## Scope

This document is the single place for release workflow guidance for `kugarocks/bookstack-content-sync`.

## Recommended first tag

Use `v0.1.0` as the first public tag.

Why `0.1.0` instead of `1.0.0`:

- the package is functional and tested
- the package is still intentionally coupled to a BookStack host runtime
- compatibility has been verified in a local host workflow, not across a broad BookStack version matrix

## Pre-release checklist

Before tagging or publishing:

- confirm the working tree is clean
- confirm `docs/release/notes-v0.1.0.md` exists and matches the intended release tag
- run `composer validate --strict`
- run `composer test`
- review `README.md` and `docs/installation-and-compatibility.md`
- confirm the local BookStack host verification is still representative
- refine `docs/release/notes-v0.1.0.md` if needed

If the release changes push behavior, repeat a controlled host-side `bookstack:push-content --execute` verification.

## Release script

Use the release helper script from the repository root:

```bash
release.sh check v0.1.0
release.sh tag v0.1.0
release.sh push v0.1.0
```

Or run the full flow in one command:

```bash
release.sh all v0.1.0
```

What each subcommand does:

- `check`: verifies a clean working tree, checks the expected release notes file, runs Composer validation, runs the test suite, and previews the release notes
- `tag`: verifies the release notes file exists, creates an annotated tag, and shows it locally
- `push`: pushes `main` and the given tag to `origin`
- `all`: runs the full sequence in order

## Manual equivalent

If you prefer to release manually:

```bash
git status --short
test -f docs/release/notes-v0.1.0.md
composer validate --strict
composer test
sed -n '1,240p' docs/release/notes-v0.1.0.md
git tag -a v0.1.0 -m "Release v0.1.0"
git show v0.1.0 --stat --no-patch
git push origin main
git push origin v0.1.0
```

## Release notes

Use `docs/release/notes-v0.1.0.md` as the base text for the first public release.

## Packagist registration helper

If the repository has already been pushed and tagged, you can register and refresh the package on Packagist with:

```bash
packagist.sh publish
```

The helper is pinned to:

- repository: `https://github.com/kugarocks/bookstack-content-sync`
- package: `kugarocks/bookstack-content-sync`

Authentication is read from these environment variables by default:

- `COMPOSER_PACKAGIST_USERNAME`
- `COMPOSER_PACKAGIST_TOKEN`

You can also pass `--username` and `--token` directly if needed.

The helper script will:

- call `create-package`
- ignore duplicate create situations with a warning
- check whether the Packagist package page is reachable
- call `update-package`

## Packagist notes

After the tag is pushed:

- confirm the repository is connected in Packagist
- trigger Packagist update if auto-sync is not active
- verify that `v0.1.0` appears as an available version

## Post-release verification

After publishing, it is still recommended to verify:

- dependency resolution from a clean host install
- package discovery inside the BookStack host
- `php artisan bookstack:pull-content --help`
- `php artisan bookstack:push-content --help`
- at least one controlled workflow relevant to the release
