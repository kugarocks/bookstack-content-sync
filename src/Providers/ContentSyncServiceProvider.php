<?php

namespace KugaRocks\BookStackContentSync\Providers;

use Illuminate\Support\ServiceProvider;
use KugaRocks\BookStackContentSync\Console\Commands\PullContentCommand;
use KugaRocks\BookStackContentSync\Console\Commands\PushContentCommand;

class ContentSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Container bindings will be added here when the real sync services are migrated.
    }

    public function boot(): void
    {
        $this->commands([
            PullContentCommand::class,
            PushContentCommand::class,
        ]);
    }
}
