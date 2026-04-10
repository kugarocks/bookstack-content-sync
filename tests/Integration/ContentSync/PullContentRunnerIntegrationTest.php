<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiClient;
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
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use Kugarocks\BookStackContentSync\Support\BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PullContentRunnerIntegrationTest extends TestCase
{
    public function test_runner_pulls_remote_tree_and_writes_local_project_files(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $http = new HttpRequestService();
        $http->mockClient([
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
                'name' => 'Blog',
                'slug' => 'blog',
                'description' => '',
                'tags' => [['name' => 'topic', 'value' => 'dev']],
                'books' => [['id' => 2, 'name' => '2026', 'slug' => '2026']],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 2,
                'name' => '2026',
                'slug' => '2026',
                'description' => 'Year notes',
                'tags' => [['name' => 'series', 'value' => 'neovim']],
                'contents' => [
                    [
                        'id' => 5,
                        'type' => 'page',
                        'name' => 'Overview',
                        'slug' => 'overview',
                        'priority' => 1,
                    ],
                    [
                        'id' => 3,
                        'type' => 'chapter',
                        'name' => 'Neovim',
                        'slug' => 'neovim',
                        'priority' => 2,
                        'pages' => [
                            [
                                'id' => 4,
                                'name' => 'Quick Start',
                                'slug' => 'quick-start',
                                'priority' => 1,
                            ],
                        ],
                    ],
                ],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 5,
                'name' => 'Overview',
                'slug' => 'overview',
                'priority' => 1,
                'markdown' => "Book-level page\n",
                'tags' => [],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 3,
                'name' => 'Neovim',
                'slug' => 'neovim',
                'description' => 'Editor notes',
                'priority' => 2,
                'tags' => [['name' => 'level', 'value' => 'advanced']],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 4,
                'name' => 'Quick Start',
                'slug' => 'quick-start',
                'priority' => 1,
                'markdown' => "Chapter page\n",
                'tags' => [['name' => 'quick-start', 'value' => '']],
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($runner, $projectRoot) {
            $result = $runner->run($projectRoot);

            $this->assertCount(5, $result->snapshotNodes);
            $this->assertFileExists($projectRoot . '/content/01-blog/_meta.yml');
            $this->assertFileExists($projectRoot . '/content/01-blog/01-2026/_meta.yml');
            $this->assertFileExists($projectRoot . '/content/01-blog/01-2026/01-overview.md');
            $this->assertFileExists($projectRoot . '/content/01-blog/01-2026/02-neovim/_meta.yml');
            $this->assertFileExists($projectRoot . '/content/01-blog/01-2026/02-neovim/01-quick-start.md');
            $this->assertFileExists($projectRoot . '/snapshot.json');

            $bookMeta = file_get_contents($projectRoot . '/content/01-blog/01-2026/_meta.yml');
            $overviewPage = file_get_contents($projectRoot . '/content/01-blog/01-2026/01-overview.md');
            $snapshot = json_decode(file_get_contents($projectRoot . '/snapshot.json'), true);

            $this->assertStringContainsString('type: "book"', $bookMeta);
            $this->assertStringContainsString('value: "neovim"', $bookMeta);
            $this->assertStringContainsString("---\ntitle: \"Overview\"\nslug: \"overview\"\n", $overviewPage);
            $this->assertStringContainsString("---\n\nBook-level page\n", $overviewPage);
            $this->assertStringNotContainsString('# Overview', $overviewPage);
            $this->assertCount(5, $snapshot['nodes']);
            $this->assertSame('shelf', $snapshot['nodes'][0]['type']);
            $this->assertSame('01-blog/01-2026/01-overview.md', $snapshot['nodes'][2]['file']);
            $this->assertSame('page', $snapshot['nodes'][4]['type']);
        });

        $this->deleteDirectory($projectRoot);
    }

    public function test_runner_fails_if_required_env_variable_missing(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);
        $runner = $this->runner(new HttpRequestService());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BOOKSTACK_API_TOKEN_SECRET');

        try {
            $this->runWithEnv([
                'BOOKSTACK_API_TOKEN_ID' => 'token-id',
                'BOOKSTACK_API_TOKEN_SECRET' => null,
            ], function () use ($runner, $projectRoot) {
                $runner->run($projectRoot);
            });
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_runner_fails_if_content_directory_is_not_empty(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);
        mkdir($projectRoot . '/content', 0777, true);
        file_put_contents($projectRoot . '/content/existing.md', 'old');

        $http = new HttpRequestService();
        $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [],
                'total' => 0,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 2]],
                'total' => 1,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 2,
                'name' => '2026',
                'slug' => '2026',
                'description' => '',
                'tags' => [],
                'contents' => [],
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target directory must be empty or not exist');

        try {
            $this->runWithEnv([
                'BOOKSTACK_API_TOKEN_ID' => 'token-id',
                'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
            ], function () use ($runner, $projectRoot) {
                $runner->run($projectRoot);
            });
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_runner_fails_if_snapshot_already_exists(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);
        file_put_contents($projectRoot . '/snapshot.json', '{}');

        $http = new HttpRequestService();
        $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [],
                'total' => 0,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 2]],
                'total' => 1,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 2,
                'name' => '2026',
                'slug' => '2026',
                'description' => '',
                'tags' => [],
                'contents' => [],
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target snapshot must not already exist');

        try {
            $this->runWithEnv([
                'BOOKSTACK_API_TOKEN_ID' => 'token-id',
                'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
            ], function () use ($runner, $projectRoot) {
                $runner->run($projectRoot);
            });
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_runner_fails_if_api_returns_error_status(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $http = new HttpRequestService();
        $http->mockClient([
            new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => [
                    'message' => 'Boom',
                ],
            ])),
        ], false);

        $runner = $this->runner($http);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BookStack API request failed');

        try {
            $this->runWithEnv([
                'BOOKSTACK_API_TOKEN_ID' => 'token-id',
                'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
            ], function () use ($runner, $projectRoot) {
                $runner->run($projectRoot);
            });
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_runner_fails_if_api_returns_invalid_json(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $http = new HttpRequestService();
        $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], '{invalid-json'),
        ], false);

        $runner = $this->runner($http);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode JSON response');

        try {
            $this->runWithEnv([
                'BOOKSTACK_API_TOKEN_ID' => 'token-id',
                'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
            ], function () use ($runner, $projectRoot) {
                $runner->run($projectRoot);
            });
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    protected function runner(HttpRequestService $http): PullContentRunner
    {
        $tagNormalizer = new TagNormalizer();

        return new PullContentRunner(
            new SyncConfigLoader(),
            new BookStackApiRemoteTreeReader(
                new BookStackApiClient($http),
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

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/pull-runner-integration-' . bin2hex(random_bytes(8));
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
