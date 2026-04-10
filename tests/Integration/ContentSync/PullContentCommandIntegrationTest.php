<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\Console\Commands\PullContentCommand;
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
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ConsoleComponentFactory;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PullContentCommandIntegrationTest extends TestCase
{
    public function test_command_pulls_remote_tree_and_reports_summary(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

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
                'description' => 'Year notes',
                'tags' => [],
                'contents' => [[
                    'id' => 5,
                    'type' => 'page',
                    'name' => 'Overview',
                    'slug' => 'overview',
                    'priority' => 1,
                ]],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 5,
                'name' => 'Overview',
                'slug' => 'overview',
                'priority' => 1,
                'markdown' => "Book-level page\n",
                'tags' => [],
            ])),
        ], false);

        $command = new PullContentCommand($this->runner($http));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($tester, $projectRoot) {
            $exitCode = $tester->execute(['projectPath' => $projectRoot]);

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Starting pull', $tester->getDisplay());
            $this->assertStringContainsString('Reading remote content tree', $tester->getDisplay());
            $this->assertStringContainsString('BOOK', $tester->getDisplay());
            $this->assertStringContainsString('2026', $tester->getDisplay());
            $this->assertStringContainsString('PAGE', $tester->getDisplay());
            $this->assertStringContainsString('Overview', $tester->getDisplay());
            $this->assertStringContainsString('Writing pulled files to disk', $tester->getDisplay());
            $this->assertStringContainsString('| METRIC         | COUNT |', $tester->getDisplay());
            $this->assertStringContainsString('| EXPORTED FILES | 2     |', $tester->getDisplay());
            $this->assertStringContainsString('| SNAPSHOT NODES | 2     |', $tester->getDisplay());
            $this->assertStringContainsString('Pull complete.', $tester->getDisplay());
            $this->assertFileExists($projectRoot . '/content/01-2026/_meta.yml');
            $this->assertFileExists($projectRoot . '/content/01-2026/01-overview.md');
            $this->assertFileExists($projectRoot . '/snapshot.json');
        });

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_reports_error_when_required_env_variable_missing(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $command = new PullContentCommand($this->runner(new HttpRequestService()));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => null,
        ], function () use ($tester, $projectRoot) {
            $exitCode = $tester->execute(['projectPath' => $projectRoot]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Pull failed.', $tester->getDisplay());
            $this->assertStringContainsString('BOOKSTACK_API_TOKEN_SECRET', $tester->getDisplay());
        });

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_suggests_init_command_when_sync_config_is_missing(): void
    {
        $projectRoot = $this->createTempDirectory();

        $command = new PullContentCommand($this->runner(new HttpRequestService()));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $projectRoot]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Pull failed.', $tester->getDisplay());
        $this->assertStringContainsString('Sync config file not found', $tester->getDisplay());
        $this->assertStringContainsString("php artisan bookstack:init-content-dir {$projectRoot}", $tester->getDisplay());

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_reports_error_when_target_directory_is_not_empty(): void
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

        $command = new PullContentCommand($this->runner($http));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($tester, $projectRoot) {
            $exitCode = $tester->execute(['projectPath' => $projectRoot]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Pull failed.', $tester->getDisplay());
            $this->assertStringContainsString('Pull target directory must be empty or not exist', $tester->getDisplay());
        });

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_reports_error_when_api_request_fails(): void
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

        $command = new PullContentCommand($this->runner($http));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($tester, $projectRoot) {
            $exitCode = $tester->execute(['projectPath' => $projectRoot]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Pull failed.', $tester->getDisplay());
            $this->assertStringContainsString('BookStack API request failed', $tester->getDisplay());
        });

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_reports_error_when_api_returns_invalid_json(): void
    {
        $projectRoot = $this->createTempDirectory();
        $this->writeSyncConfig($projectRoot);

        $http = new HttpRequestService();
        $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], '{invalid-json'),
        ], false);

        $command = new PullContentCommand($this->runner($http));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $this->runWithEnv([
            'BOOKSTACK_API_TOKEN_ID' => 'token-id',
            'BOOKSTACK_API_TOKEN_SECRET' => 'token-secret',
        ], function () use ($tester, $projectRoot) {
            $exitCode = $tester->execute(['projectPath' => $projectRoot]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Pull failed.', $tester->getDisplay());
            $this->assertStringContainsString('Failed to decode JSON response', $tester->getDisplay());
        });

        $this->deleteDirectory($projectRoot);
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

    protected function consoleContainer(): Container
    {
        $container = new class extends Container
        {
            public function runningUnitTests(): bool
            {
                return true;
            }
        };
        $container->bind(OutputStyle::class, function ($container, array $parameters) {
            return new OutputStyle($parameters['input'], $parameters['output']);
        });
        $container->bind(ConsoleComponentFactory::class, function ($container, array $parameters) {
            return new ConsoleComponentFactory($parameters['output']);
        });

        return $container;
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
        $path = sys_get_temp_dir() . '/pull-command-integration-' . bin2hex(random_bytes(8));
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
