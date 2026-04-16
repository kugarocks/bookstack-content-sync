<?php

namespace Kugarocks\BookStackContentSync\Console\Commands;

use Illuminate\Console\Command;
use JsonException;

class InitContentProjectCommand extends Command
{
    protected $signature = 'bookstack:init-content-dir {projectPath} {--app-url=https://docs.example.com} {--content-path=content} {--token-id-env=BOOKSTACK_API_TOKEN_ID} {--token-secret-env=BOOKSTACK_API_TOKEN_SECRET} {--bookstack-path=}';
    protected $description = 'Initialize a local content project directory for BookStack sync';

    public function handle(): int
    {
        $projectPath = rtrim((string) $this->argument('projectPath'), '/');
        $syncPath = $projectPath . '/sync.json';

        if (is_file($syncPath)) {
            $this->newLine();
            $this->error("Sync config already exists at [{$syncPath}]");
            $this->newLine();

            return self::FAILURE;
        }

        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0777, true);
        }

        $contentPath = trim((string) $this->option('content-path'), '/');
        $tokenIdEnv = trim((string) $this->option('token-id-env'));
        $tokenSecretEnv = trim((string) $this->option('token-secret-env'));
        $bookstackPath = trim((string) $this->option('bookstack-path'));

        if ($bookstackPath === '') {
            $bookstackPath = getcwd() ?: '';
        }

        try {
            $json = json_encode([
                'version' => 1,
                'app_url' => (string) $this->option('app-url'),
                'bookstack_path' => $bookstackPath,
                'content_path' => $contentPath,
                'env_vars' => [
                    'token_id' => $tokenIdEnv,
                    'token_secret' => $tokenSecretEnv,
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            $this->newLine();
            $this->error('Failed to build sync.json');
            $this->newLine();

            return self::FAILURE;
        }

        $editorConfigPath = $projectPath . '/.editorconfig';
        $editorConfig = <<<'EDITORCONFIG'
root = true

[*.json]
indent_style = space
indent_size = 4

[*.jsonc]
indent_style = space
indent_size = 4
EDITORCONFIG;

        file_put_contents($syncPath, $json . PHP_EOL);
        file_put_contents($editorConfigPath, $editorConfig . PHP_EOL);

        $this->newLine();
        $this->info("Initialized content sync project at [{$projectPath}]");
        $this->line("Created <fg=white>{$syncPath}</>");
        $this->line("Created <fg=white>{$editorConfigPath}</>");
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Next steps</>');
        $this->line("  1. Review <fg=white>{$syncPath}</> and update <fg=white>app_url</> if needed.");
        $this->line("  2. Export <fg=white>{$tokenIdEnv}</> and <fg=white>{$tokenSecretEnv}</> in your shell.");
        $this->line("  3. Run <fg=white>php artisan bookstack:pull-content {$projectPath}</>.");
        $this->newLine();

        return self::SUCCESS;
    }
}
