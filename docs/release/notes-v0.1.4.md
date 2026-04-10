# Release Notes: v0.1.4

## Summary

`v0.1.4` is a tag format cleanup release for `kugarocks/bookstack-content-sync`.
It simplifies local content tag handling by standardizing on `name` and `name:value` string forms in YAML, while keeping BookStack API tag mapping intact.

## What is included

- local tag model renamed from `key` + `value` to `name` + `value`
- simplified YAML tag syntax for pulled and pushed content
- support for tag entries written as `"name"` and `"name:value"`
- rejection of legacy object-style tag YAML entries such as `key` / `value`
- updated pull and push tests for the new tag format

## Verification completed

This release has been verified with the following checks:

- `php vendor/bin/phpunit tests/Unit/ContentSync/Push/LocalFileParserTest.php tests/Unit/ContentSync/Pull/PageFileBuilderTest.php tests/Unit/ContentSync/Pull/MetaFileBuilderTest.php tests/Unit/ContentSync/Pull/BookStackApiRemoteTreeReaderTest.php`
- `php vendor/bin/phpunit tests/Integration/ContentSync/PullContentRunnerIntegrationTest.php tests/Integration/ContentSync/PushContentRunnerIntegrationTest.php`

## Notes for adopters

- Content files should now express tags only as YAML strings.
- Use `"name"` for a tag without a value.
- Use `"name:value"` for a tag with an explicit value.
- Forms such as `":value"`, `"name:"`, or nested object-style tag entries are no longer accepted.

## Suggested short release text

Tag format cleanup release that standardizes YAML tags as `"name"` and `"name:value"` strings and removes the older object-style tag format.
