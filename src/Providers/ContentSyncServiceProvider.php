<?php

namespace Kugarocks\BookStackContentSync\Providers;

use Illuminate\Support\ServiceProvider;
use Kugarocks\BookStackContentSync\Console\Commands\InitContentProjectCommand;
use Kugarocks\BookStackContentSync\Console\Commands\PullContentCommand;
use Kugarocks\BookStackContentSync\Console\Commands\PushContentCommand;
use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiRemoteTreeReader;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullRemoteTreeReader;
use Kugarocks\BookStackContentSync\Support\BookStack\HostVersionGuard;

class ContentSyncServiceProvider extends ServiceProvider
{
    public array $bindings = [
        PullRemoteTreeReader::class => BookStackApiRemoteTreeReader::class,
    ];

    public function register(): void
    {
        (new HostVersionGuard())->ensureSupportedVersion();

        // Additional bindings will be added here as more sync features are migrated.
    }

    public function boot(): void
    {
        $this->commands([
            InitContentProjectCommand::class,
            PullContentCommand::class,
            PushContentCommand::class,
        ]);
    }
}
