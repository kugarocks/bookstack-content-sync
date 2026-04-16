# Release Notes: v0.2.2

## Summary

`v0.2.2` adds `.editorconfig` generation to the `init-content-dir` command for `kugarocks/bookstack-content-sync`.

## What is included

- `bookstack:init-content-dir` now writes `.editorconfig` alongside `sync.json`
- `.editorconfig` enforces 4-space indentation for JSON and JSONC files

## Verification completed

This release has been verified with the following checks:

- PHP syntax validation for updated command files
- Manual testing of `bookstack:init-content-dir` command

## Suggested short release text

Adds `.editorconfig` generation to `init-content-dir` for consistent JSON formatting in content directories.
