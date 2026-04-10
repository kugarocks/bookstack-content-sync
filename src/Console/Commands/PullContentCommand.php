<?php

namespace KugaRocks\BookStackContentSync\Console\Commands;

use Illuminate\Console\Command;

class PullContentCommand extends Command
{
    protected $signature = 'bookstack:pull-content {projectPath}';
    protected $description = 'Pull BookStack content into a local content project';

    public function handle(): int
    {
        $this->warn('bookstack-content-sync skeleton installed. Pull implementation has not been migrated yet.');
        $this->line('Target project path: ' . (string) $this->argument('projectPath'));

        return self::SUCCESS;
    }
}
