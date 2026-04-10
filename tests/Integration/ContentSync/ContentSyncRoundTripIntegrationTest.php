<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiClient as PullBookStackApiClient;
use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiRemoteTreeReader;
use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullContentRunner;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullPathBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullResultBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullResultWriter;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\BookStackApiClient as PushBookStackApiClient;
use Kugarocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalProjectStateWriter;
use Kugarocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushContentRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanExecutor;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use BookStack\Http\HttpClientHistory;
use BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ContentSyncRoundTripIntegrationTest extends TestCase
{
    public function test_pull_then_edit_then_push_round_trip_uses_compatible_project_format(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $pullHttp = new HttpRequestService();
        $pullHttp->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 1]],
                'total' => 1,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 2]],
                'total' => 1,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'name' => 'Guides',
                'slug' => 'guides',
                'description' => '',
                'tags' => [],
                'books' => [['id' => 2, 'name' => 'Laravel', 'slug' => 'laravel']],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 2,
                'name' => 'Laravel',
                'slug' => 'laravel',
                'description' => 'Laravel notes',
                'tags' => [],
                'contents' => [
                    [
                        'id' => 10,
                        'type' => 'page',
                        'name' => 'Intro',
                        'slug' => 'intro',
                        'priority' => 1,
                    ],
                ],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 10,
                'name' => 'Intro',
                'slug' => 'intro',
                'priority' => 1,
                'tags' => [],
            ])),
            new Response(200, ['Content-Type' => 'text/plain'], "# Intro\n\nOriginal intro body\n"),
        ], false);

        $pullRunner = $this->pullRunner($pullHttp);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($pullRunner, $projectRoot): void {
            $pullRunner->run($projectRoot);
        });

        file_put_contents($projectRoot . '/content/01-guides/01-laravel/01-intro.md', <<<MD
---
title: "Intro Updated"
slug: "intro-updated"
tags: []
entity_id: 10
---

Updated intro body
MD);

        mkdir($projectRoot . '/content/01-guides/01-laravel/02-setup', 0777, true);
        file_put_contents($projectRoot . '/content/01-guides/01-laravel/02-setup/_meta.yml', <<<YAML
type: "chapter"
title: "Setup"
slug: "setup"
desc: ""
tags: []
YAML);
        file_put_contents($projectRoot . '/content/01-guides/01-laravel/02-setup/01-first-run.md', <<<MD
---
title: "First Run"
slug: "first-run"
tags: []
---

First run body
MD);

        $pushHttp = new HttpRequestService();
        $pushHistory = $pushHttp->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 20, 'slug' => 'setup'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 21, 'slug' => 'first-run'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 10, 'slug' => 'intro-updated'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'guides'])),
        ], false);

        $pushRunner = $this->pushRunner($pushHttp);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($pushRunner, $projectRoot, $pushHistory): void {
            $pushRunner->run($projectRoot);

            $this->assertSame('/api/chapters', $pushHistory->requestAt(0)?->getUri()->getPath());
            $this->assertSame('/api/pages', $pushHistory->requestAt(1)?->getUri()->getPath());
            $this->assertSame('/api/pages/10', $pushHistory->requestAt(2)?->getUri()->getPath());
            $this->assertNull($pushHistory->requestAt(3));

            $chapterBody = $this->decodeRequestBody($pushHistory, 0);
            $newPageBody = $this->decodeRequestBody($pushHistory, 1);
            $updatedPageBody = $this->decodeRequestBody($pushHistory, 2);

            $this->assertSame('Setup', $chapterBody['name']);
            $this->assertSame('setup', $chapterBody['slug']);
            $this->assertSame(2, $chapterBody['book_id']);

            $this->assertSame('First Run', $newPageBody['name']);
            $this->assertSame('first-run', $newPageBody['slug']);
            $this->assertSame(20, $newPageBody['chapter_id']);

            $this->assertSame('Intro Updated', $updatedPageBody['name']);
            $this->assertSame('intro-updated', $updatedPageBody['slug']);
            $this->assertSame('Updated intro body', trim($updatedPageBody['markdown']));
        });

        $chapterMeta = file_get_contents($projectRoot . '/content/01-guides/01-laravel/02-setup/_meta.yml');
        $newPageFile = file_get_contents($projectRoot . '/content/01-guides/01-laravel/02-setup/01-first-run.md');
        $snapshot = json_decode(file_get_contents($projectRoot . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        $nodesById = [];
        foreach ($snapshot['nodes'] as $node) {
            $nodesById[$node['entity_id']] = $node;
        }

        $this->assertStringContainsString('entity_id: 20', $chapterMeta);
        $this->assertStringContainsString('entity_id: 21', $newPageFile);
        $this->assertSame('intro-updated', $nodesById[10]['slug']);
        $this->assertSame('01-guides/01-laravel/02-setup', $nodesById[20]['file']);
        $this->assertSame('01-guides/01-laravel/02-setup/01-first-run.md', $nodesById[21]['file']);

        $this->deleteDirectory($projectRoot);
    }

    protected function pullRunner(HttpRequestService $http): PullContentRunner
    {
        $tagNormalizer = new TagNormalizer();

        return new PullContentRunner(
            new SyncConfigLoader(),
            new BookStackApiRemoteTreeReader(
                new PullBookStackApiClient($http),
                new SyncConfigEnvCredentialResolver(),
            ),
            new PullResultBuilder(
                new PullPathBuilder(),
                new MetaFileBuilder($tagNormalizer),
                new PageFileBuilder($tagNormalizer),
                new SnapshotBuilder(new ContentHashBuilder($tagNormalizer)),
            ),
            new PullResultWriter(new SnapshotJsonBuilder()),
        );
    }

    protected function pushRunner(HttpRequestService $http): PushContentRunner
    {
        return new PushContentRunner(
            new PushProjectStateLoader(
                new SyncConfigLoader(),
                new SnapshotFileLoader(),
                new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
                new ProjectStructureValidator(),
            ),
            new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            new PushPlanExecutor(
                new PushBookStackApiClient($http),
                new SyncConfigEnvCredentialResolver(),
                new LocalProjectStateWriter(
                    new MetaFileBuilder(new TagNormalizer()),
                    new PageFileBuilder(new TagNormalizer()),
                    new SnapshotJsonBuilder(),
                ),
            ),
        );
    }

    protected function writeSyncConfig(string $projectRoot): void
    {
        file_put_contents($projectRoot . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
    }

    protected function decodeRequestBody(HttpClientHistory $history, int $index): array
    {
        $request = $history->requestAt($index);

        return json_decode((string) $request?->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/content-sync-roundtrip-' . bin2hex(random_bytes(8));
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

    /**
     * @param array<string, ?string> $valuesByKey
     */
    protected function runWithEnv(array $valuesByKey, callable $callback): void
    {
        $originals = [];

        foreach ($valuesByKey as $key => $value) {
            $originals[$key] = $_SERVER[$key] ?? null;
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
            foreach ($originals as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                    putenv($key);
                    continue;
                }

                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
