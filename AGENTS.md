# Codex Rules

This repository is maintained with Codex assistance. Follow these rules for every change unless the user says otherwise.

## Working style

- Keep changes focused and minimal; avoid unrelated refactors.
- After making requested changes, proactively create a git commit without waiting for an extra reminder.
- Do not rewrite or revert user changes that are unrelated to the task.
- Prefer updating existing docs when behavior, commands, paths, or release steps change.

## Repository-specific expectations

- Helper scripts live at the repository root: `packagist.sh` and `release.sh`.
- Do not introduce package autoload mappings under the `BookStack\\` namespace.
- Keep package-owned code under `Kugarocks\\BookStackContentSync\\...` to avoid collisions with BookStack host classes.
- Treat this package as a BookStack host extension, not a generic standalone Laravel package.

## Validation

- Run the smallest relevant test or verification command for the change before finishing.
- For script/help/doc updates, at least run the affected script help output when practical.
- If you cannot run a needed validation step, say so clearly in the final response.

## Release and documentation

- When release workflow changes, update `docs/release/guide.md` and the relevant release notes file in `docs/release/`.
- Keep command examples aligned with the actual script entrypoints and current repository conventions.

## Response expectations

- Report what changed, what was validated, and the commit hash you created.
- Keep answers concise and practical.
