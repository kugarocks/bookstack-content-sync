<?php

namespace Tests\Integration\ContentSync;

use KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use KugaRocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use KugaRocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use KugaRocks\BookStackContentSync\ContentSync\Push\PlanAction;
use KugaRocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use KugaRocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use KugaRocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use KugaRocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class PushPlanRunnerIntegrationTest extends TestCase
{
    public function test_runner_builds_push_plan_from_project_files(): void
    {
        $root = sys_get_temp_dir() . '/push-plan-runner-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-blog/01-book', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-blog',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'blog',
                    'name' => 'Blog',
                    'content_hash' => 'hash-blog',
                ],
                [
                    'file' => '01-blog/01-book',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'book',
                    'name' => 'Book',
                    'content_hash' => 'hash-book',
                ],
                [
                    'file' => '01-blog/01-book/01-old.md',
                    'type' => 'page',
                    'entity_id' => 10,
                    'position' => 1,
                    'slug' => 'old',
                    'name' => 'Old',
                    'content_hash' => 'hash-old',
                ],
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-blog/_meta.yml', <<<YAML
type: "shelf"
title: "Blog"
slug: "blog"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-blog/01-book/_meta.yml', <<<YAML
type: "book"
title: "Book"
slug: "book"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/01-blog/01-book/01-new.md', <<<MD
---
title: "New Page"
slug: "new"
tags: []
---

Body
MD);

        $runner = new PushPlanRunner(
            new PushProjectStateLoader(
                new SyncConfigLoader(),
                new SnapshotFileLoader(),
                new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                new ProjectStructureValidator(),
            ),
            new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
        );

        $plan = $runner->run($root);

        $this->assertCount(4, $plan->items());
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Create));
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Trash));

        $this->deleteDirectory($root);
    }

    public function test_runner_marks_shelf_membership_sync_when_books_change_shelves(): void
    {
        $root = sys_get_temp_dir() . '/push-plan-runner-membership-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-shelf-a/01-new-book', 0777, true);
        mkdir($root . '/content/02-shelf-b/01-existing-book', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-shelf-a/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf A"
slug: "shelf-a"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/02-shelf-b/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf B"
slug: "shelf-b"
desc: ""
tags: []
entity_id: 5
YAML);
        file_put_contents($root . '/content/01-shelf-a/01-new-book/_meta.yml', <<<YAML
type: "book"
title: "New Book"
slug: "new-book"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/02-shelf-b/01-existing-book/_meta.yml', <<<YAML
type: "book"
title: "Existing Book"
slug: "existing-book"
desc: ""
tags: []
entity_id: 2
YAML);

        $scanner = new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer())));
        $localNodes = $scanner->scan($root, 'content');
        $hashes = [];
        foreach ($localNodes as $localNode) {
            $hashes[$localNode->path] = $localNode->contentHash;
        }

        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-shelf-a',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'shelf-a',
                    'name' => 'Shelf A',
                    'content_hash' => $hashes['content/01-shelf-a'],
                ],
                [
                    'file' => '02-shelf-b',
                    'type' => 'shelf',
                    'entity_id' => 5,
                    'position' => 2,
                    'slug' => 'shelf-b',
                    'name' => 'Shelf B',
                    'content_hash' => $hashes['content/02-shelf-b'],
                ],
                [
                    'file' => '01-shelf-a/01-existing-book',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'existing-book',
                    'name' => 'Existing Book',
                    'content_hash' => $hashes['content/02-shelf-b/01-existing-book'],
                    'parent' => [
                        'entity_id' => 1,
                        'type' => 'shelf',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $runner = new PushPlanRunner(
            new PushProjectStateLoader(
                new SyncConfigLoader(),
                new SnapshotFileLoader(),
                new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                new ProjectStructureValidator(),
            ),
            new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
        );

        $plan = $runner->run($root);

        $this->assertCount(2, $plan->itemsForAction(PlanAction::SyncMembership));
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Create));
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Move));

        $this->deleteDirectory($root);
    }

    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
