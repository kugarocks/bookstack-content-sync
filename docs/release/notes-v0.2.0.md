# Release Notes: v0.2.0

## Summary

`v0.2.0` refines the push workflow, introduces a global wrapper script for running commands from any directory, and adds `.editorconfig` generation for consistent JSON formatting.

## What is included

### Push Workflow Refinements

- Local snapshot preview support in push planning, including local-only path and metadata refreshes
- Cleaner execute output that no longer repeats planned changes after remote execution
- Structured push progress events instead of command rendering logic coupled to raw progress strings
- Internal push-plan preparation extracted into shared preparation classes
- Local snapshot projection split into explicit persisted and preview projection flows
- Reserved empty-page transport placeholder for remote page markdown:
  `<!-- bookstack-content-sync:empty-page:v1 -->`
- Remote slug normalization handling that warns the user, updates local files, and updates `snapshot.json`
- README clarification for host slug support expectations and compatibility behavior

### Global Wrapper Script

- Global wrapper script `bin/bookstack-sync` with config and init command support
- Global configuration file at `~/.config/bookstack-content-sync/config.json` for persistent BookStack path
- Path resolution priority: `sync.json` > global config > error
- `bookstack-sync config set-bookstack-path` command to persist BookStack installation path
- `bookstack-sync init` command to initialize content directories from anywhere
- `bookstack-sync pull/push` commands that work from content directory
- `InitContentProjectCommand` now accepts `--bookstack-path` option and writes it to `sync.json`
- Updated README with Global Wrapper section documenting setup and usage

### Editor Configuration

- `bookstack:init-content-dir` now writes `.editorconfig` alongside `sync.json`
- `.editorconfig` enforces 4-space indentation for JSON and JSONC files

## Verification completed

This release has been verified with the following checks:

- `composer test-push`
- `php vendor/bin/phpunit tests/Integration/ContentSync/PushContentCommandIntegrationTest.php tests/Integration/ContentSync/PushContentRunnerIntegrationTest.php tests/Integration/ContentSync/ContentSyncRoundTripIntegrationTest.php`
- Local host verification with `php artisan bookstack:push-content sync-demo`
- Local host verification with `php artisan bookstack:push-content sync-demo --execute`
- Shell syntax validation with `bash -n bin/bookstack-sync`
- PHP syntax validation for updated command files
- Manual testing of wrapper script functionality

## Notes for adopters

### Push Workflow

- Push plan output may now report `Local Snapshot Updates` even when no remote API changes are required
- Empty page content now uses a reserved remote transport placeholder:
  `<!-- bookstack-content-sync:empty-page:v1 -->`
- Local page semantics remain unchanged: an empty page is still `""`
- Pull decodes that reserved placeholder back to `""` before writing local page files
- Hashing, diffing, and `snapshot.json` all use the decoded semantic value, so an empty page is treated as an empty string throughout local state
- If the BookStack host does not preserve requested slugs through the API, push execution now treats the remote slug as the source of truth
- In that slug-normalization case, the command warns, then rewrites the local file slug and `snapshot.json` slug to the remote value
- A host that includes [the BookStack custom slug support change](https://github.com/kugarocks/BookStack/commit/e6c75b4d13dab676424461c210b14f730c2a6ad3) supports custom slugs for those content entity APIs

### Global Wrapper

- The wrapper script is optional and does not affect existing `php artisan` workflows
- Users can install the wrapper by symlinking `bin/bookstack-sync` to `/usr/local/bin/bookstack-sync`
- One-time setup requires running `bookstack-sync config set-bookstack-path /path/to/bookstack`
- After setup, all commands (`init`, `pull`, `push`) can be run from any content directory
- The `bookstack_path` field in `sync.json` takes precedence over global config

## Suggested short release text

Push workflow refinement with local snapshot previews, reserved empty-page remote transport handling, cleaner execute output, remote slug normalization, global wrapper script for running commands from any directory, and `.editorconfig` generation for consistent JSON formatting.
