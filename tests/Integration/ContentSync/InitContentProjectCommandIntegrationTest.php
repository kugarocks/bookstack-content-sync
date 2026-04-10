<?php

namespace Tests\Integration\ContentSync;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ConsoleComponentFactory;
use Illuminate\Container\Container;
use Kugarocks\BookStackContentSync\Console\Commands\InitContentProjectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitContentProjectCommandIntegrationTest extends TestCase
{
    public function test_command_creates_project_directory_and_sync_config(): void
    {
        $projectRoot = sys_get_temp_dir() . '/init-content-dir-' . bin2hex(random_bytes(8));

        $command = new InitContentProjectCommand();
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $projectRoot]);

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($projectRoot);
        $this->assertFileExists($projectRoot . '/sync.json');
        $this->assertStringContainsString('Initialized content sync project', $tester->getDisplay());
        $this->assertStringContainsString('BOOKSTACK_API_TOKEN_ID', $tester->getDisplay());
        $this->assertStringContainsString('BOOKSTACK_API_TOKEN_SECRET', $tester->getDisplay());
        $this->assertStringContainsString("php artisan bookstack:pull-content {$projectRoot}", $tester->getDisplay());
        $this->assertStringContainsString('"app_url": "https://docs.example.com"', file_get_contents($projectRoot . '/sync.json'));
        $this->assertStringContainsString('"content_path": "content"', file_get_contents($projectRoot . '/sync.json'));

        $this->deleteDirectory($projectRoot);
    }

    public function test_command_fails_when_sync_config_already_exists(): void
    {
        $projectRoot = sys_get_temp_dir() . '/init-content-dir-' . bin2hex(random_bytes(8));
        mkdir($projectRoot, 0777, true);
        file_put_contents($projectRoot . '/sync.json', '{}');

        $command = new InitContentProjectCommand();
        $command->setLaravel($this->consoleContainer());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['projectPath' => $projectRoot]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Sync config already exists', $tester->getDisplay());

        $this->deleteDirectory($projectRoot);
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
