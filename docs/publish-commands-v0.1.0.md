# Publish Commands: v0.1.0

## Assumptions

Use this sequence when you are ready to publish the first tag for `kugarocks/bookstack-content-sync`.

Assumptions:

- you are in `/Users/kuga/github/kugarocks/bookstack-content-sync`
- the working tree is clean
- `v0.1.0` is still the intended first tag
- the local host verification on `BookStack` has already been completed

## Final pre-tag checks

```bash
git status --short
composer validate --strict
composer test
```

Optional archive check to confirm `todo/` is excluded from release archives:

```bash
git archive --worktree-attributes --format=tar HEAD | tar -tf - | rg '^todo/'
```

Expected result: no output.

## Review release text

Open and refine the first release draft if needed:

```bash
sed -n '1,240p' docs/release-notes-v0.1.0.md
```

## Create the tag

Annotated tag recommended:

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
```

If needed, confirm the tag locally:

```bash
git tag --list | rg '^v0\.1\.0$'
git show v0.1.0 --stat --no-patch
```

## Push branch and tag

```bash
git push origin main
git push origin v0.1.0
```

## Publish on Packagist

After the tag is pushed:

- ensure the repository is connected in Packagist
- trigger Packagist update if auto-sync is not active
- verify that `v0.1.0` appears as an available version

## Suggested release payload

Use `docs/release-notes-v0.1.0.md` as the base text for:

- Git hosting release notes
- changelog entry if you add one later
- Packagist-facing release summary where relevant
