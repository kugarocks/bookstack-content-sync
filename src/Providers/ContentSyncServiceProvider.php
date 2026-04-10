<?php

namespace KugaRocks\BookStackContentSync\Providers;

use Illuminate\Support\ServiceProvider;
use KugaRocks\BookStackContentSync\Console\Commands\PullContentCommand;
use KugaRocks\BookStackContentSync\Console\Commands\PushContentCommand;
use KugaRocks\BookStackContentSync\ContentSync\Pull\BookStackApiRemoteTreeReader;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullRemoteTreeReader;

class ContentSyncServiceProvider extends ServiceProvider
{
    public array $bindings = [
        PullRemoteTreeReader::class => BookStackApiRemoteTreeReader::class,
    ];

    public function register(): void
    {
        // Additional bindings will be added here as more sync features are migrated.
    }

    public function boot(): void
    {
        $this->commands([
            PullContentCommand::class,
            PushContentCommand::class,
        ]);
    }
}
