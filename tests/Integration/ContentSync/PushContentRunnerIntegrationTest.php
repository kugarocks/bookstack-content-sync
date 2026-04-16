<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\BookStackApiClient;
use Kugarocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalProjectStateWriter;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalSnapshotProjector;
use Kugarocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushContentRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanExecutor;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanPreparer;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use Kugarocks\BookStackContentSync\Support\BookStack\Http\HttpClientHistory;
use Kugarocks\BookStackContentSync\Support\BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PushContentRunnerIntegrationTest extends TestCase
{
    public function test_runner_executes_create_update_and_trash_then_rewrites_local_state(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-guides/01-laravel/02-new-chapter', 0777, true);
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/_meta.yml', <<<YAML
type: "book"
title: "Laravel"
slug: "laravel"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/01-intro.md', <<<MD
---
title: "Intro Updated"
slug: "intro"
tags: []
entity_id: 10
---

Updated intro body
MD);
        file_put_contents($root . '/content/01-guides/01-laravel/02-new-chapter/_meta.yml', <<<YAML
type: "chapter"
title: "Setup"
slug: "setup"
desc: "Setup steps"
tags:
  - "topic:install"
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/02-new-chapter/01-first-run.md', <<<MD
---
title: "First Run"
slug: "first-run"
tags:
  - "quickstart"
---

Boot the app
MD);

        $scanner = $this->scanner();
        $localNodes = $scanner->scan($root, 'content');
        $hashes = [];
        foreach ($localNodes as $localNode) {
            $hashes[$localNode->path] = $localNode->contentHash;
        }

        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-guides',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'guides',
                    'name' => 'Guides',
                    'content_hash' => $hashes['content/01-guides'],
                ],
                [
                    'file' => '01-guides/01-laravel',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'laravel',
                    'name' => 'Laravel',
                    'content_hash' => $hashes['content/01-guides/01-laravel'],
                ],
                [
                    'file' => '01-guides/01-laravel/01-intro.md',
                    'type' => 'page',
                    'entity_id' => 10,
                    'position' => 1,
                    'slug' => 'intro',
                    'name' => 'Intro',
                    'content_hash' => 'hash-old',
                ],
                [
                    'file' => '01-guides/01-laravel/03-removed.md',
                    'type' => 'page',
                    'entity_id' => 11,
                    'position' => 3,
                    'slug' => 'removed',
                    'name' => 'Removed',
                    'content_hash' => 'hash-removed',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 30, 'slug' => 'setup'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 31, 'slug' => 'first-run'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 10, 'slug' => 'intro'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'guides'])),
            new Response(204, [], ''),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history): void {
            $plan = $runner->run($root)->plan;

            $this->assertCount(6, $plan->items());
            $this->assertSame('/api/chapters', $history->requestAt(0)?->getUri()->getPath());
            $this->assertSame('/api/pages', $history->requestAt(1)?->getUri()->getPath());
            $this->assertSame('/api/pages/10', $history->requestAt(2)?->getUri()->getPath());
            $this->assertSame('/api/shelves/1', $history->requestAt(3)?->getUri()->getPath());
            $this->assertSame('/api/pages/11', $history->requestAt(4)?->getUri()->getPath());

            $chapterBody = $this->decodeRequestBody($history, 0);
            $pageBody = $this->decodeRequestBody($history, 1);
            $updateBody = $this->decodeRequestBody($history, 2);
            $shelfBody = $this->decodeRequestBody($history, 3);

            $this->assertSame(2, $chapterBody['book_id']);
            $this->assertSame('Setup', $chapterBody['name']);
            $this->assertSame('setup', $chapterBody['slug']);
            $this->assertSame(30, $pageBody['chapter_id']);
            $this->assertSame('First Run', $pageBody['name']);
            $this->assertSame('first-run', $pageBody['slug']);
            $this->assertSame('Intro Updated', $updateBody['name']);
            $this->assertSame('intro', $updateBody['slug']);
            $this->assertSame([2], $shelfBody['books']);

            $chapterMeta = file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/_meta.yml');
            $pageFile = file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/01-first-run.md');
            $snapshot = json_decode(file_get_contents($root . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);

            $this->assertStringContainsString('entity_id: 30', $chapterMeta);
            $this->assertStringContainsString('entity_id: 31', $pageFile);
            $this->assertCount(5, $snapshot['nodes']);
            $this->assertSame(30, $snapshot['nodes'][3]['entity_id']);
            $this->assertSame(31, $snapshot['nodes'][4]['entity_id']);
        });

        $this->deleteDirectory($root);
    }

    public function test_runner_syncs_shelf_book_membership_for_created_and_moved_books(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-shelf-a/01-new-book', 0777, true);
        mkdir($root . '/content/02-shelf-b/01-existing-book', 0777, true);
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

        $scanner = $this->scanner();
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
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 9, 'slug' => 'new-book'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2, 'slug' => 'existing-book'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'shelf-a'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 5, 'slug' => 'shelf-b'])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history): void {
            $runner->run($root);

            $this->assertSame('/api/books', $history->requestAt(0)?->getUri()->getPath());
            $this->assertSame('/api/books/2', $history->requestAt(1)?->getUri()->getPath());
            $this->assertSame('/api/shelves/1', $history->requestAt(2)?->getUri()->getPath());
            $this->assertSame('/api/shelves/5', $history->requestAt(3)?->getUri()->getPath());
            $this->assertSame('new-book', $this->decodeRequestBody($history, 0)['slug']);
            $this->assertSame('existing-book', $this->decodeRequestBody($history, 1)['slug']);
            $this->assertSame([9], $this->decodeRequestBody($history, 2)['books']);
            $this->assertSame([2], $this->decodeRequestBody($history, 3)['books']);

            $newBookMeta = file_get_contents($root . '/content/01-shelf-a/01-new-book/_meta.yml');
            $snapshot = json_decode(file_get_contents($root . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);

            $this->assertStringContainsString('entity_id: 9', $newBookMeta);
            $this->assertCount(4, $snapshot['nodes']);
        });

        $this->deleteDirectory($root);
    }

    public function test_runner_uses_separator_placeholder_for_empty_page_content(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-work/01-oschina', 0777, true);
        file_put_contents($root . '/content/01-work/_meta.yml', <<<YAML
type: "shelf"
title: "Work"
slug: "work"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-work/01-oschina/_meta.yml', <<<YAML
type: "book"
title: "OSChina"
slug: "oschina"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-work/01-oschina/01-empty.md', <<<MD
---
title: "Empty Page"
slug: "empty-page"
tags: []
---

MD);
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 1,
            'nodes' => [],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'work'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2, 'slug' => 'oschina'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 3, 'slug' => 'empty-page'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'work'])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history): void {
            $runner->run($root);

            $pageBody = $this->decodeRequestBody($history, 2);
            $pageFile = file_get_contents($root . '/content/01-work/01-oschina/01-empty.md');

            $this->assertSame(PageFileBuilder::EMPTY_PAGE_MARKDOWN_PLACEHOLDER, $pageBody['markdown']);
            $this->assertStringEndsWith(PageFileBuilder::EMPTY_PAGE_MARKDOWN_PLACEHOLDER, $pageFile);
            $this->assertStringContainsString('entity_id: 3', $pageFile);
        });

        $this->deleteDirectory($root);
    }

    public function test_runner_applies_rename_and_move_updates_for_existing_chapter_and_page(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-guides/01-first-book/01-renamed-chapter', 0777, true);
        mkdir($root . '/content/01-guides/02-second-book', 0777, true);
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-guides/01-first-book/_meta.yml', <<<YAML
type: "book"
title: "First Book"
slug: "first-book"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/01-guides/02-second-book/_meta.yml', <<<YAML
type: "book"
title: "Second Book"
slug: "second-book"
desc: ""
tags: []
entity_id: 3
YAML);
        file_put_contents($root . '/content/01-guides/01-first-book/01-renamed-chapter/_meta.yml', <<<YAML
type: "chapter"
title: "Renamed Chapter"
slug: "renamed-chapter"
desc: ""
tags: []
entity_id: 20
YAML);
        file_put_contents($root . '/content/01-guides/01-first-book/01-renamed-chapter/01-moved-page.md', <<<MD
---
title: "Moved Page"
slug: "moved-page"
tags: []
entity_id: 21
---

Moved page body
MD);

        $scanner = $this->scanner();
        $localNodes = $scanner->scan($root, 'content');
        $hashes = [];
        foreach ($localNodes as $localNode) {
            $hashes[$localNode->path] = $localNode->contentHash;
        }

        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-guides',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'guides',
                    'name' => 'Guides',
                    'content_hash' => $hashes['content/01-guides'],
                ],
                [
                    'file' => '01-guides/01-first-book',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'first-book',
                    'name' => 'First Book',
                    'content_hash' => $hashes['content/01-guides/01-first-book'],
                ],
                [
                    'file' => '01-guides/02-second-book',
                    'type' => 'book',
                    'entity_id' => 3,
                    'position' => 2,
                    'slug' => 'second-book',
                    'name' => 'Second Book',
                    'content_hash' => $hashes['content/01-guides/02-second-book'],
                ],
                [
                    'file' => '01-guides/02-second-book/01-old-chapter',
                    'type' => 'chapter',
                    'entity_id' => 20,
                    'position' => 1,
                    'slug' => 'old-chapter',
                    'name' => 'Old Chapter',
                    'content_hash' => 'hash-old-chapter',
                ],
                [
                    'file' => '01-guides/02-second-book/01-old-chapter/01-old-page.md',
                    'type' => 'page',
                    'entity_id' => 21,
                    'position' => 1,
                    'slug' => 'old-page',
                    'name' => 'Old Page',
                    'content_hash' => 'hash-old-page',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 20, 'slug' => 'renamed-chapter'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 21, 'slug' => 'moved-page'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'guides'])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history): void {
            $runner->run($root);

            $this->assertSame('/api/chapters/20', $history->requestAt(0)?->getUri()->getPath());
            $this->assertSame('/api/pages/21', $history->requestAt(1)?->getUri()->getPath());
            $this->assertSame('/api/shelves/1', $history->requestAt(2)?->getUri()->getPath());

            $chapterBody = $this->decodeRequestBody($history, 0);
            $pageBody = $this->decodeRequestBody($history, 1);

            $this->assertSame('Renamed Chapter', $chapterBody['name']);
            $this->assertSame('renamed-chapter', $chapterBody['slug']);
            $this->assertSame(2, $chapterBody['book_id']);
            $this->assertSame('Moved Page', $pageBody['name']);
            $this->assertSame('moved-page', $pageBody['slug']);
            $this->assertSame(20, $pageBody['chapter_id']);

            $snapshot = json_decode(file_get_contents($root . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
            $nodesById = [];
            foreach ($snapshot['nodes'] as $node) {
                $nodesById[$node['entity_id']] = $node;
            }

            $this->assertSame('01-guides/01-first-book/01-renamed-chapter', $nodesById[20]['file']);
            $this->assertSame('renamed-chapter', $nodesById[20]['slug']);
            $this->assertSame('01-guides/01-first-book/01-renamed-chapter/01-moved-page.md', $nodesById[21]['file']);
            $this->assertSame('moved-page', $nodesById[21]['slug']);
        });

        $this->deleteDirectory($root);
    }

    public function test_runner_does_not_rewrite_local_state_when_execution_fails_midway(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-guides/01-laravel/02-new-chapter', 0777, true);
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/_meta.yml', <<<YAML
type: "book"
title: "Laravel"
slug: "laravel"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/02-new-chapter/_meta.yml', <<<YAML
type: "chapter"
title: "Setup"
slug: "setup"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/02-new-chapter/01-first-run.md', <<<MD
---
title: "First Run"
slug: "first-run"
tags: []
---

Boot the app
MD);
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [
                [
                    'file' => '01-guides',
                    'type' => 'shelf',
                    'entity_id' => 1,
                    'position' => 1,
                    'slug' => 'guides',
                    'name' => 'Guides',
                    'content_hash' => 'hash-shelf',
                ],
                [
                    'file' => '01-guides/01-laravel',
                    'type' => 'book',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'laravel',
                    'name' => 'Laravel',
                    'content_hash' => 'hash-book',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $originalChapterMeta = file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/_meta.yml');
        $originalPageFile = file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/01-first-run.md');
        $originalSnapshot = file_get_contents($root . '/snapshot.json');

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 30, 'slug' => 'setup'])),
            new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => ['message' => 'Boom'],
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history, $originalChapterMeta, $originalPageFile, $originalSnapshot): void {
            try {
                $runner->run($root);
                $this->fail('Expected push execution to fail');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('[pages]', $exception->getMessage());
            }

            $this->assertSame('/api/chapters', $history->requestAt(0)?->getUri()->getPath());
            $this->assertSame('/api/pages', $history->requestAt(1)?->getUri()->getPath());
            $this->assertSame($originalChapterMeta, file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/_meta.yml'));
            $this->assertSame($originalPageFile, file_get_contents($root . '/content/01-guides/01-laravel/02-new-chapter/01-first-run.md'));
            $this->assertSame($originalSnapshot, file_get_contents($root . '/snapshot.json'));
        });

        $this->deleteDirectory($root);
    }

    public function test_runner_accepts_slug_mismatch_and_preserves_local_slug(): void
    {
        $root = $this->createTempDirectory();
        $this->writeSyncConfig($root);

        mkdir($root . '/content/01-guides/01-laravel', 0777, true);
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
YAML);
        file_put_contents($root . '/content/01-guides/01-laravel/_meta.yml', <<<YAML
type: "book"
title: "Laravel"
slug: "laravel-local"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [[
                'file' => '01-guides',
                'type' => 'shelf',
                'entity_id' => 1,
                'position' => 1,
                'slug' => 'guides',
                'name' => 'Guides',
                'content_hash' => 'hash-guides',
            ]],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 9,
                'slug' => 'laravel-remote',
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'slug' => 'guides',
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'slug' => 'guides',
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $root, $history): void {
            $result = $runner->run($root);

            $this->assertCount(2, $result->plan->items());
            $this->assertSame('/api/books', $history->requestAt(0)?->getUri()->getPath());

            $updatedBookMeta = file_get_contents($root . '/content/01-guides/01-laravel/_meta.yml');
            $this->assertNotFalse($updatedBookMeta);
            $this->assertStringContainsString('slug: "laravel-remote"', $updatedBookMeta);
            $this->assertStringContainsString('entity_id: 9', $updatedBookMeta);
            $this->assertStringNotContainsString('slug: "laravel-local"', $updatedBookMeta);

            $snapshot = json_decode(file_get_contents($root . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(2, $snapshot['nodes']);
            $this->assertSame('laravel-remote', $snapshot['nodes'][1]['slug']);
        });

        $this->deleteDirectory($root);
    }

    protected function runner(HttpRequestService $http): PushContentRunner
    {
        [$stateLoader, $pushPlanBuilder, $localSnapshotProjector] = $this->pushComponents();

        return new PushContentRunner(
            new PushPlanPreparer($stateLoader, $pushPlanBuilder),
            new PushPlanExecutor(
                new BookStackApiClient($http),
                new SyncConfigEnvCredentialResolver(),
                new LocalProjectStateWriter(
                    new MetaFileBuilder(new TagNormalizer()),
                    new PageFileBuilder(new TagNormalizer()),
                    new SnapshotJsonBuilder(),
                    $localSnapshotProjector,
                    new ContentHashBuilder(new TagNormalizer()),
                ),
                $localSnapshotProjector,
            ),
        );
    }

    protected function scanner(): LocalContentScanner
    {
        return new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer())));
    }

    /**
     * @return array{PushProjectStateLoader, PushPlanBuilder, LocalSnapshotProjector}
     */
    protected function pushComponents(): array
    {
        $tagNormalizer = new TagNormalizer();
        $localSnapshotProjector = new LocalSnapshotProjector();

        return [
            new PushProjectStateLoader(
                new SyncConfigLoader(),
                new SnapshotFileLoader(),
                new LocalContentScanner(new LocalFileParser(new ContentHashBuilder($tagNormalizer))),
                new ProjectStructureValidator(),
            ),
            new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            $localSnapshotProjector,
        ];
    }

    protected function writeSyncConfig(string $root): void
    {
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @param array<string, ?string> $variables
     */
    protected function runWithEnv(array $variables, callable $callback): void
    {
        $original = [];

        foreach ($variables as $key => $value) {
            $original[$key] = $_SERVER[$key] ?? getenv($key) ?: null;

            if ($value === null) {
                unset($_SERVER[$key]);
                putenv($key);
                continue;
            }

            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            $callback();
        } finally {
            foreach ($variables as $key => $_value) {
                $previous = $original[$key];

                if ($previous === null || $previous === false) {
                    unset($_SERVER[$key]);
                    putenv($key);
                    continue;
                }

                $_SERVER[$key] = $previous;
                putenv("{$key}={$previous}");
            }
        }
    }

    protected function decodeRequestBody(HttpClientHistory $history, int $index): array
    {
        $request = $history->requestAt($index);

        return json_decode((string) $request?->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/push-content-runner-' . bin2hex(random_bytes(8));
        mkdir($path, 0777, true);

        return $path;
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
