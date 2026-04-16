<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalSnapshotProjector;
use Kugarocks\BookStackContentSync\ContentSync\Push\PlanAction;
use Kugarocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanPreparer;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\PageMarkdownCodec;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
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
            new PushPlanPreparer(
                new PushProjectStateLoader(
                    new SyncConfigLoader(),
                    new SnapshotFileLoader(),
                    new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                    new ProjectStructureValidator(),
                ),
                new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            ),
            new LocalSnapshotProjector(),
        );

        $plan = $runner->run($root)->plan;

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
            new PushPlanPreparer(
                new PushProjectStateLoader(
                    new SyncConfigLoader(),
                    new SnapshotFileLoader(),
                    new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                    new ProjectStructureValidator(),
                ),
                new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            ),
            new LocalSnapshotProjector(),
        );

        $plan = $runner->run($root)->plan;

        $this->assertCount(2, $plan->itemsForAction(PlanAction::SyncMembership));
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Create));
        $this->assertCount(1, $plan->itemsForAction(PlanAction::Move));

        $this->deleteDirectory($root);
    }

    public function test_runner_treats_remote_empty_page_placeholder_as_noop_for_local_empty_page(): void
    {
        $root = sys_get_temp_dir() . '/push-plan-runner-empty-page-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-work/01-oschina', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-work/_meta.yml', <<<YAML
type: "shelf"
title: "Work"
slug: "work"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-work/01-oschina/_meta.yml', <<<YAML
type: "book"
title: "OSChina"
slug: "oschina"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/01-work/01-oschina/01-empty.md', <<<MD
---
title: "Empty Page"
slug: "empty-page"
tags: []
entity_id: 3
---

MD);

        $hashBuilder = new ContentHashBuilder(new TagNormalizer());
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-work',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'work',
                    'name' => 'Work',
                    'content_hash' => $hashBuilder->build(new ContentHashData(
                        type: NodeType::Shelf,
                        name: 'Work',
                        slug: 'work',
                        description: '',
                        tags: [],
                    )),
                ],
                [
                    'file' => '01-work/01-oschina',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'oschina',
                    'name' => 'OSChina',
                    'content_hash' => $hashBuilder->build(new ContentHashData(
                        type: NodeType::Book,
                        name: 'OSChina',
                        slug: 'oschina',
                        description: '',
                        tags: [],
                    )),
                    'parent' => [
                        'entity_id' => 1,
                        'type' => 'shelf',
                    ],
                ],
                [
                    'file' => '01-work/01-oschina/01-empty.md',
                    'type' => 'page',
                    'entity_id' => 3,
                    'position' => 1,
                    'slug' => 'empty-page',
                    'name' => 'Empty Page',
                    'content_hash' => $hashBuilder->build(new ContentHashData(
                        type: NodeType::Page,
                        name: 'Empty Page',
                        slug: 'empty-page',
                        markdown: PageMarkdownCodec::EMPTY_PAGE_REMOTE_PLACEHOLDER,
                        tags: [],
                    )),
                    'parent' => [
                        'entity_id' => 2,
                        'type' => 'book',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $runner = new PushPlanRunner(
            new PushPlanPreparer(
                new PushProjectStateLoader(
                    new SyncConfigLoader(),
                    new SnapshotFileLoader(),
                    new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                    new ProjectStructureValidator(),
                ),
                new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            ),
            new LocalSnapshotProjector(),
        );

        $result = $runner->run($root);

        $this->assertCount(0, $result->plan->itemsForAction(PlanAction::Update));
        $this->assertSame([], $result->localSnapshotChanges);

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
