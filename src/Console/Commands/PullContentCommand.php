<?php

namespace Kugarocks\BookStackContentSync\Console\Commands;

use Kugarocks\BookStackContentSync\ContentSync\Pull\PullContentRunner;
use Illuminate\Console\Command;
use Throwable;

class PullContentCommand extends Command
{
    protected $signature = 'bookstack:pull-content {projectPath}';
    protected $description = 'Pull BookStack content into a local content project';

    public function __construct(
        protected PullContentRunner $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectPath = (string) $this->argument('projectPath');
        $syncPath = rtrim($projectPath, '/') . '/sync.json';

        try {
            $this->renderStage('Starting pull', 'info');
            $result = $this->runner->run($projectPath, fn (string $message, string $tone = 'info') => $this->renderStage($message, $tone));
        } catch (Throwable $exception) {
            $this->renderStage('Pull failed.', 'error');
            $this->error($exception->getMessage());
            if (str_contains($exception->getMessage(), "Sync config file not found at path [{$syncPath}]")) {
                $this->newLine();
                $this->line(sprintf(
                    '<fg=yellow>Tip:</> run <fg=white>php artisan bookstack:init-content-project %s</> first, then export the required API token environment variables.',
                    $projectPath
                ));
            }
            $this->finishOutput();

            return self::FAILURE;
        }

        $this->renderSummaryTable(count($result->exportFilePlans), count($result->snapshotNodes));
        $this->newLine();
        $this->renderStage('Pull complete.', 'success');
        $this->finishOutput();

        return self::SUCCESS;
    }

    protected function renderStage(string $message, string $tone = 'info'): void
    {
        if ($message === 'Reading remote content tree') {
            $this->newLine();
        }

        if ($tone === 'error') {
            $this->newLine();
        }

        if (preg_match('/^Pulling (shelf|book|chapter|page): (.+)$/', $message, $matches)) {
            $typeKey = strtolower($matches[1]);
            $type = strtoupper($typeKey);
            $name = $matches[2];

            $this->line(sprintf(
                '  <fg=%s;options=bold>%-7s</>  <fg=white>%s</>',
                $this->entityColor($typeKey),
                $type,
                $name
            ));

            return;
        }

        if ($message === 'Building local export plan') {
            $this->newLine();
        }

        [$icon, $color] = match ($tone) {
            'success' => ['OK', 'green'],
            'error' => ['ERR', 'red'],
            'warn' => ['!', 'yellow'],
            default => ['>', 'cyan'],
        };

        $this->line(sprintf('<fg=%s;options=bold>%s</> %s', $color, $icon, $message));
    }

    protected function renderStat(string $label, int $value): void
    {
        $this->line(sprintf('<fg=default>%s:</> <fg=white;options=bold>%d</>', $label, $value));
    }

    protected function renderSummaryTable(int $exportedFiles, int $snapshotNodes): void
    {
        $this->newLine();
        $this->table(['METRIC', 'COUNT'], [
            ['EXPORTED FILES', (string) $exportedFiles],
            ['SNAPSHOT NODES', (string) $snapshotNodes],
        ]);
    }

    protected function entityColor(string $type): string
    {
        return match ($type) {
            'shelf' => 'yellow',
            'book' => 'blue',
            'chapter' => 'magenta',
            'page' => 'cyan',
            default => 'white',
        };
    }

    protected function finishOutput(): void
    {
        $this->newLine();
    }
}
