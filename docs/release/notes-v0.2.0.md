# Release Notes: v0.2.0

## Summary

`v0.2.0` refines the push workflow for `kugarocks/bookstack-content-sync`.
Push planning now previews local snapshot changes more clearly, execute output is less noisy, and slug normalization from BookStack is handled by updating local files and `snapshot.json` to the remote result.

## What is included

- local snapshot preview support in push planning, including local-only path and metadata refreshes
- cleaner execute output that no longer repeats planned changes after remote execution
- structured push progress events instead of command rendering logic coupled to raw progress strings
- internal push-plan preparation extracted into shared preparation classes
- local snapshot projection split into explicit persisted and preview projection flows
- remote slug normalization handling that warns the user, updates local files, and updates `snapshot.json`
- README clarification for host slug support expectations and compatibility behavior

## Verification completed

This release has been verified with the following checks:

- `composer test-push`
- `php vendor/bin/phpunit tests/Integration/ContentSync/PushContentCommandIntegrationTest.php tests/Integration/ContentSync/PushContentRunnerIntegrationTest.php tests/Integration/ContentSync/ContentSyncRoundTripIntegrationTest.php`
- local host verification with `php artisan bookstack:push-content sync-demo`
- local host verification with `php artisan bookstack:push-content sync-demo --execute`

## Notes for adopters

- Push plan output may now report `Local Snapshot Updates` even when no remote API changes are required.
- If the BookStack host does not preserve requested slugs through the API, push execution now treats the remote slug as the source of truth.
- In that slug-normalization case, the command warns, then rewrites the local file slug and `snapshot.json` slug to the remote value.
- A host that includes [the BookStack custom slug support change](https://github.com/kugarocks/BookStack/commit/e6c75b4d13dab676424461c210b14f730c2a6ad3) supports custom slugs for those content entity APIs.

## Suggested short release text

Push workflow refinement release with local snapshot previews, cleaner execute output, and remote slug normalization that rewrites local files and snapshot state to match BookStack.
