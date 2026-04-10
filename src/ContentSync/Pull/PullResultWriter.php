<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use InvalidArgumentException;

class PullResultWriter
{
    public function __construct(
        protected SnapshotJsonBuilder $snapshotJsonBuilder,
    ) {
    }

    public function write(string $projectRootPath, SyncConfig $config, PullResult $result): void
    {
        $projectRootPath = rtrim($projectRootPath, '/');

        if (!is_dir($projectRootPath)) {
            mkdir($projectRootPath, 0777, true);
        }

        $this->assertWritableTargetsAreEmpty($projectRootPath, $config);

        foreach ($result->exportFilePlans as $plan) {
            $this->writeFile($projectRootPath . '/' . ltrim($plan->path, '/'), $plan->contents);
        }

        $snapshotPath = $projectRootPath . '/snapshot.json';
        $this->writeFile($snapshotPath, $this->snapshotJsonBuilder->build($result->snapshotNodes));
    }

    public function assertWritableTargetsAreEmpty(string $projectRootPath, SyncConfig $config): void
    {
        $contentRootPath = $projectRootPath . '/' . trim($config->contentPath, '/');
        if ($this->directoryExistsAndIsNotEmpty($contentRootPath)) {
            throw new InvalidArgumentException("Pull target directory must be empty or not exist [{$contentRootPath}]");
        }

        $snapshotPath = $projectRootPath . '/snapshot.json';
        if (is_file($snapshotPath)) {
            throw new InvalidArgumentException("Pull target snapshot must not already exist [{$snapshotPath}]");
        }
    }

    protected function directoryExistsAndIsNotEmpty(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = scandir($path);

        if ($items === false) {
            return false;
        }

        return count(array_diff($items, ['.', '..'])) > 0;
    }

    protected function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
    }
}
