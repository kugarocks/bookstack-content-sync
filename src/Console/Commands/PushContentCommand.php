<?php

namespace KugaRocks\BookStackContentSync\Console\Commands;

use Illuminate\Console\Command;

class PushContentCommand extends Command
{
    protected $signature = 'bookstack:push-content {projectPath} {--e|execute}';
    protected $description = 'Build or execute a push plan for a local content project';

    public function handle(): int
    {
        $mode = $this->option('execute') ? 'push execution' : 'push planning';

        $this->warn('bookstack-content-sync skeleton installed. Push implementation has not been migrated yet.');
        $this->line('Requested mode: ' . $mode);
        $this->line('Target project path: ' . (string) $this->argument('projectPath'));

        return self::SUCCESS;
    }
}
