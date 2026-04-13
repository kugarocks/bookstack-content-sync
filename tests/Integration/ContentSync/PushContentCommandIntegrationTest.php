<?php

namespace Tests\Integration\ContentSync;

use Kugarocks\BookStackContentSync\Console\Commands\PushContentCommand;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use Kugarocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Push\BookStackApiClient;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalProjectStateWriter;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalSnapshotProjector;
use Kugarocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushContentRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanExecutor;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanPreparer;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use Kugarocks\BookStackContentSync\Support\BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ConsoleComponentFactory;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PushContentCommandIntegrationTest extends TestCase
{
    public function test_command_builds_push_plan_and_reports_summary(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-' . bin2hex(random_bytes(8));
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

        $command = new PushContentCommand($this->runner(), $this->pushRunner(new HttpRequestService()));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $root]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Starting push plan', $tester->getDisplay());
        $this->assertStringContainsString('Loading local project state', $tester->getDisplay());
        $this->assertStringContainsString('Building push plan', $tester->getDisplay());
        $this->assertStringContainsString('Planned changes', $tester->getDisplay());
        $this->assertStringContainsString('CREATE', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/page\\s+content\\/01-blog\\/01-book\\/01-new\\.md \\(New Page\\)/', $tester->getDisplay());
        $this->assertStringContainsString('TRASH', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/page\\s+01-blog\\/01-book\\/01-old\\.md \\(Old\\)/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+ACTION\\s+\\|\\s+COUNT\\s+\\|/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+ITEMS\\s+\\|\\s+4\\s+\\|/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+CREATE\\s+\\|\\s+1\\s+\\|/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+MEMBERSHIP\\s+\\|\\s+1\\s+\\|/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+TRASH\\s+\\|\\s+1\\s+\\|/', $tester->getDisplay());
        $this->assertStringContainsString("\nOK Push plan complete.\n\n", $tester->getDisplay());

        $this->deleteDirectory($root);
    }

    public function test_command_reports_error_when_project_state_is_invalid(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-invalid-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);

        $command = new PushContentCommand($this->runner(), $this->pushRunner(new HttpRequestService()));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $root]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Push plan failed.', $tester->getDisplay());

        $this->deleteDirectory($root);
    }

    public function test_command_treats_shelf_prefix_renumber_as_local_only_change(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-shelf-renumber-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/02-shelf-b/01-book-b1', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/02-shelf-b/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf B"
slug: "shelf-b"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/02-shelf-b/01-book-b1/_meta.yml', <<<YAML
type: "book"
title: "Book B1"
slug: "book-b1"
desc: ""
tags: []
entity_id: 5
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
                    'file' => '01-shelf-b',
                    'type' => 'shelf',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'shelf-b',
                    'name' => 'Shelf B',
                    'content_hash' => $hashes['content/02-shelf-b'],
                ],
                [
                    'file' => '01-shelf-b/01-book-b1',
                    'type' => 'book',
                    'entity_id' => 5,
                    'position' => 1,
                    'slug' => 'book-b1',
                    'name' => 'Book B1',
                    'content_hash' => $hashes['content/02-shelf-b/01-book-b1'],
                    'parent' => [
                        'entity_id' => 2,
                        'type' => 'shelf',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $command = new PushContentCommand($this->runner(), $this->pushRunner(new HttpRequestService()));
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $root]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No remote API changes required', $tester->getDisplay());
        $this->assertStringContainsString('Local Snapshot Updates', $tester->getDisplay());
        $this->assertStringContainsString('01-shelf-b', $tester->getDisplay());
        $this->assertStringContainsString('02-shelf-b', $tester->getDisplay());
        $this->assertStringNotContainsString('| ACTION | COUNT |', $tester->getDisplay());

        $this->deleteDirectory($root);
    }

    public function test_command_executes_push_and_reports_summary(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-execute-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-guides', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
YAML);

        $scanner = new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer())));
        $localNode = $scanner->scan($root, 'content')[0];
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [[
                'file' => '01-guides',
                'type' => 'shelf',
                'entity_id' => 1,
                'position' => 1,
                'slug' => 'guides',
                'name' => 'Guides',
                'content_hash' => $localNode->contentHash,
            ]],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'guides'])),
        ], false);

        $tester = $this->commandTester($http);
        $exitCode = $this->executeWithApiEnv($tester, ['projectPath' => $root, '--execute' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Starting push', $tester->getDisplay());
        $this->assertStringContainsString('No changes required', $tester->getDisplay());
        $this->assertStringNotContainsString('Executing remote changes', $tester->getDisplay());
        $this->assertStringNotContainsString('Syncing shelf membership', $tester->getDisplay());
        $this->assertStringNotContainsString('Writing updated local metadata', $tester->getDisplay());
        $this->assertStringNotContainsString('Local Snapshot Updates', $tester->getDisplay());
        $this->assertStringNotContainsString('Push complete.', $tester->getDisplay());
        $this->assertSame(0, $history->requestCount());

        $this->deleteDirectory($root);
    }

    public function test_command_execute_with_remote_changes_does_not_repeat_planned_changes(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-execute-remote-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-guides', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-guides/_meta.yml', <<<YAML
type: "shelf"
title: "Guides"
slug: "guides"
desc: ""
tags: []
entity_id: 1
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
                'content_hash' => 'hash-old-guides',
            ]],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'slug' => 'guides'])),
        ], false);

        $tester = $this->commandTester($http);
        $exitCode = $this->executeWithApiEnv($tester, ['projectPath' => $root, '--execute' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Starting push', $tester->getDisplay());
        $this->assertStringContainsString('Executing remote changes', $tester->getDisplay());
        $this->assertStringContainsString('Push complete.', $tester->getDisplay());
        $this->assertStringNotContainsString('Planned changes', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/\\|\\s+UPDATE\\s+\\|\\s+1\\s+\\|/', $tester->getDisplay());
        $this->assertSame(1, $history->requestCount());

        $this->deleteDirectory($root);
    }

    public function test_command_reports_local_snapshot_updates_when_execute_has_no_remote_changes(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-execute-local-snapshot-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/02-shelf-b/01-book-b1', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/02-shelf-b/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf B"
slug: "shelf-b"
desc: ""
tags: []
entity_id: 2
YAML);
        file_put_contents($root . '/content/02-shelf-b/01-book-b1/_meta.yml', <<<YAML
type: "book"
title: "Book B1"
slug: "book-b1"
desc: ""
tags: []
entity_id: 5
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
                    'file' => '01-shelf-b',
                    'type' => 'shelf',
                    'entity_id' => 2,
                    'position' => 1,
                    'slug' => 'shelf-b',
                    'name' => 'Shelf B',
                    'content_hash' => $hashes['content/02-shelf-b'],
                ],
                [
                    'file' => '01-shelf-b/01-book-b1',
                    'type' => 'book',
                    'entity_id' => 5,
                    'position' => 1,
                    'slug' => 'book-b1',
                    'name' => 'Book B1',
                    'content_hash' => $hashes['content/02-shelf-b/01-book-b1'],
                    'parent' => [
                        'entity_id' => 2,
                        'type' => 'shelf',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $http = new HttpRequestService();
        $history = $http->mockClient([], false);

        $tester = $this->commandTester($http);
        $exitCode = $this->executeWithApiEnv($tester, ['projectPath' => $root, '--execute' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No remote API changes required', $tester->getDisplay());
        $this->assertStringContainsString('Local Snapshot Updates', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/shelf\\s+02-shelf-b \\(Shelf B\\)/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/book\\s+02-shelf-b\\/01-book-b1 \\(Book B1\\)/', $tester->getDisplay());
        $this->assertStringContainsString('file:', $tester->getDisplay());
        $this->assertStringContainsString('01-shelf-b', $tester->getDisplay());
        $this->assertStringContainsString('02-shelf-b', $tester->getDisplay());
        $this->assertSame(0, $history->requestCount());

        $snapshot = json_decode(file_get_contents($root . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('02-shelf-b', $snapshot['nodes'][0]['file']);
        $this->assertSame('02-shelf-b/01-book-b1', $snapshot['nodes'][1]['file']);

        $this->deleteDirectory($root);
    }

    public function test_command_reports_api_failure_during_execute_with_request_path(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-execute-fail-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-guides/01-laravel', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
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
        $http->mockClient([
            new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => ['message' => 'Slug already exists'],
            ])),
        ], false);

        $tester = $this->commandTester($http);
        $exitCode = $this->executeWithApiEnv($tester, ['projectPath' => $root, '--execute' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Push failed.', $tester->getDisplay());
        $this->assertStringContainsString('[books]', $tester->getDisplay());
        $this->assertStringContainsString('status [500]', $tester->getDisplay());

        $this->deleteDirectory($root);
    }

    public function test_command_reports_slug_validation_failure_during_execute(): void
    {
        $root = sys_get_temp_dir() . '/push-content-command-execute-slug-mismatch-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-guides/01-laravel', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
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
        $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 9,
                'slug' => 'laravel-remote',
            ])),
        ], false);

        $tester = $this->commandTester($http);
        $exitCode = $this->executeWithApiEnv($tester, ['projectPath' => $root, '--execute' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Push failed.', $tester->getDisplay());
        $this->assertStringContainsString('Push slug validation failed', $tester->getDisplay());
        $this->assertStringContainsString('laravel-local', $tester->getDisplay());
        $this->assertStringContainsString('laravel-remote', $tester->getDisplay());

        $this->deleteDirectory($root);
    }

    protected function runner(): PushPlanRunner
    {
        [$stateLoader, $pushPlanBuilder, $localSnapshotProjector] = $this->pushComponents();

        return new PushPlanRunner(new PushPlanPreparer($stateLoader, $pushPlanBuilder), $localSnapshotProjector);
    }

    protected function pushRunner(HttpRequestService $http): PushContentRunner
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
                ),
                $localSnapshotProjector,
            ),
        );
    }

    protected function commandTester(HttpRequestService $http): CommandTester
    {
        $command = new PushContentCommand($this->runner(), $this->pushRunner($http));
        $command->setLaravel($this->consoleContainer());

        return new CommandTester($command);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function executeWithApiEnv(CommandTester $tester, array $arguments): int
    {
        $originalId = $_SERVER['BOOKSTACK_API_TOKEN_ID'] ?? getenv('BOOKSTACK_API_TOKEN_ID') ?: null;
        $originalSecret = $_SERVER['BOOKSTACK_API_TOKEN_SECRET'] ?? getenv('BOOKSTACK_API_TOKEN_SECRET') ?: null;
        $_SERVER['BOOKSTACK_API_TOKEN_ID'] = 'token-id';
        $_SERVER['BOOKSTACK_API_TOKEN_SECRET'] = 'token-secret';
        putenv('BOOKSTACK_API_TOKEN_ID=token-id');
        putenv('BOOKSTACK_API_TOKEN_SECRET=token-secret');

        try {
            return $tester->execute($arguments);
        } finally {
            if ($originalId === null || $originalId === false) {
                unset($_SERVER['BOOKSTACK_API_TOKEN_ID']);
                putenv('BOOKSTACK_API_TOKEN_ID');
            } else {
                $_SERVER['BOOKSTACK_API_TOKEN_ID'] = $originalId;
                putenv('BOOKSTACK_API_TOKEN_ID=' . $originalId);
            }

            if ($originalSecret === null || $originalSecret === false) {
                unset($_SERVER['BOOKSTACK_API_TOKEN_SECRET']);
                putenv('BOOKSTACK_API_TOKEN_SECRET');
            } else {
                $_SERVER['BOOKSTACK_API_TOKEN_SECRET'] = $originalSecret;
                putenv('BOOKSTACK_API_TOKEN_SECRET=' . $originalSecret);
            }
        }
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
