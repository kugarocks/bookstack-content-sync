# Release Notes: v0.2.1

## Summary

`v0.2.1` introduces a global wrapper script for `kugarocks/bookstack-content-sync` that enables running commands from any content directory without navigating to the BookStack installation.

## What is included

- Global wrapper script `bin/bookstack-sync` with config and init command support
- Global configuration file at `~/.config/bookstack-content-sync/config.json` for persistent BookStack path
- Path resolution priority: `sync.json` > global config > error
- `bookstack-sync config set-bookstack-path` command to persist BookStack installation path
- `bookstack-sync init` command to initialize content directories from anywhere
- `bookstack-sync pull/push` commands that work from content directory
- `InitContentProjectCommand` now accepts `--bookstack-path` option and writes it to `sync.json`
- Updated README with Global Wrapper section documenting setup and usage

## Verification completed

This release has been verified with the following checks:

- Shell syntax validation with `bash -n bin/bookstack-sync`
- PHP syntax validation for updated command files
- Manual testing of wrapper script functionality

## Notes for adopters

- The wrapper script is optional and does not affect existing `php artisan` workflows
- Users can install the wrapper by symlinking `bin/bookstack-sync` to `/usr/local/bin/bookstack-sync`
- One-time setup requires running `bookstack-sync config set-bookstack-path /path/to/bookstack`
- After setup, all commands (`init`, `pull`, `push`) can be run from any content directory
- The `bookstack_path` field in `sync.json` takes precedence over global config

## Suggested short release text

Global wrapper script release enabling `bookstack-sync` commands from any directory with persistent configuration support.
