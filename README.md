# bookstack-content-sync

Pulling BookStack content into a local directory, making changes, and pushing it back.

## Requirements

- PHP `>=8.2`
- BookStack `>=26.03`

## Quick Start

### Initialize Directory

Create a local content directory and starter `sync.json`:

```bash
php artisan bookstack:init-content-dir /path/to/content
```

<img src="https://raw.githubusercontent.com/kugarocks/bookstack-content-sync/main/docs/images/init-content-dir.png" alt="init-content-dir" width="680" />

This command creates the target directory if needed, writes `sync.json`, and reminds you which environment variables to export before running a pull.

```json
{
    "version": 1,
    "app_url": "http://localhost:8080",
    "bookstack_path": "/path/to/bookstack",
    "content_path": "content",
    "env_vars": {
        "token_id": "BOOKSTACK_API_TOKEN_ID",
        "token_secret": "BOOKSTACK_API_TOKEN_SECRET"
    }
}
```

### Pull Content

```bash
php artisan bookstack:pull-content /path/to/content
```

<img src="https://raw.githubusercontent.com/kugarocks/bookstack-content-sync/main/docs/images/pull-content.png" alt="pull-content" width="400" />

### Push Plan

```bash
php artisan bookstack:push-content /path/to/content
```

<img src="https://raw.githubusercontent.com/kugarocks/bookstack-content-sync/main/docs/images/push-plan.png" alt="push-plan" width="500" />

### Push Execution

```bash
php artisan bookstack:push-content /path/to/content --execute
```

<img src="https://raw.githubusercontent.com/kugarocks/bookstack-content-sync/main/docs/images/push-execution.png" alt="push-execution" width="580" />

## How it Works

This system performs a one-way sync from local content to BookStack by computing state differences and applying them to the remote.

### Push Mechanism

- Sync is one-way: local content is the source of truth.
- `bookstack:push-content` builds the current local state.
- It compares this state with the previous `snapshot.json`.
- Based on the diff, it determines and executes the required remote actions.

### Behavior & Constraints

- **Data constraint**
  - A book can belong to only one shelf at a time.
- **Naming & ordering**
  - Prefixes such as `01-xxx`, `02-xxx` are used to define ordering.
  - Items are sorted lexicographically based on these prefixes.
- **Renaming behavior**
  - Renaming local files or directories only updates the `file` field in `snapshot.json`.
  - It does not affect the identity of the corresponding remote entity.

### Slug Behavior

- Official BookStack does not preserve custom slugs for content entities via the API.
- Hosts with [custom slug support](https://github.com/kugarocks/BookStack/commit/e6c75b4d13dab676424461c210b14f730c2a6ad3) enable this behavior.
- If the requested slug is not preserved, `push-content` treats the remote slug as the source of truth.
- The command emits a warning and rewrites both the local file slug and the `snapshot.json` slug to match the remote value.

### Empty Page Behavior

- Local empty pages remain `""`.
- When pushing an empty page, the remote transport uses the reserved placeholder `<!-- bookstack-content-sync:empty-page:v1 -->`.
- When pulling, that placeholder is decoded back to `""`, and `snapshot.json` plus content hashing continue to use the decoded empty-string value.

## Installation

### Packagist

```bash
composer require kugarocks/bookstack-content-sync
```

### Local Development

For local development against an unpublished working tree, add the local repository to the BookStack host `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../bookstack-content-sync",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then require the package from the BookStack host:

```bash
composer require kugarocks/bookstack-content-sync:*@dev
```

The `@dev` suffix is only needed for local path installation while the package is resolved as `dev-main`.

## Global Wrapper

Create a global command that can be run from any content directory:

```bash
# From a BookStack installation
ln -sf /path/to/bookstack/vendor/kugarocks/bookstack-content-sync/bin/bookstack-sync /usr/local/bin/bookstack-sync

# From this repository
ln -sf /path/to/bookstack-content-sync/bin/bookstack-sync /usr/local/bin/bookstack-sync
```

The wrapper reads `sync.json` in your current directory and runs the matching BookStack artisan command using `bookstack_path`.

### Setup

Set the global BookStack path (one-time setup):

```bash
bookstack-sync config set-bookstack-path /path/to/bookstack
```

This creates `~/.config/bookstack-content-sync/config.json` with your BookStack installation path.

### Usage

```bash
# Initialize a new content directory
bookstack-sync init /path/to/content

# Pull content
bookstack-sync pull

# Generate push plan
bookstack-sync push

# Execute push
bookstack-sync push --execute
```

### Configuration

Path resolution priority:

1. `bookstack_path` in current directory's `sync.json`
2. Global config at `~/.config/bookstack-content-sync/config.json`
3. Error if neither is found

Example `sync.json` with project-specific path:

```json
{
    "version": 1,
    "app_url": "http://localhost:8080",
    "bookstack_path": "/path/to/bookstack",
    "content_path": "content",
    "env_vars": {
        "token_id": "BOOKSTACK_API_TOKEN_ID",
        "token_secret": "BOOKSTACK_API_TOKEN_SECRET"
    }
}
```

When running `bookstack-sync init`, the `bookstack_path` is automatically written to `sync.json` using the global config value.

## Testing

Install dependencies and run the full package test suite:

```bash
composer install
composer test
```

Test boundaries:

- Unit tests validate isolated sync logic.
- Integration tests execute real local file reads and writes in temporary directories.
- Integration tests do not call a real BookStack server; HTTP is mocked through the package test shim.

Run only unit tests:

```bash
composer test-unit
```

Run only integration tests:

```bash
composer test-integration
```

Run pull-focused tests:

```bash
composer test-pull
```

Run push-focused tests:

```bash
composer test-push
```
