<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalContentScanner
{
    public function __construct(
        protected LocalFileParser $fileParser,
    ) {
    }

    /**
     * @return LocalNode[]
     */
    public function scan(string $projectRootPath, string $contentPath): array
    {
        $scanRoot = rtrim($projectRootPath, '/') . '/' . trim($contentPath, '/');
        if (!is_dir($scanRoot)) {
            throw new InvalidArgumentException("Content directory not found at path [{$scanRoot}]");
        }

        $nodes = [];
        $directoryIterator = new RecursiveDirectoryIterator($scanRoot, RecursiveDirectoryIterator::SKIP_DOTS);
        $this->assertEntityDirectoriesHaveMetaFiles($directoryIterator, $projectRootPath, $scanRoot);

        $iterator = new RecursiveIteratorIterator(
            $directoryIterator
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePath = ltrim(substr($absolutePath, strlen(rtrim($projectRootPath, '/'))), '/');

            if (basename($relativePath) === '_meta.yml') {
                $nodes[] = $this->fileParser->parseMeta(file_get_contents($absolutePath), $relativePath);
                continue;
            }

            if (pathinfo($relativePath, PATHINFO_EXTENSION) === 'md') {
                $nodes[] = $this->fileParser->parsePage(file_get_contents($absolutePath), $relativePath);
            }
        }

        usort($nodes, fn (LocalNode $a, LocalNode $b) => $a->path <=> $b->path);

        return $nodes;
    }

    protected function assertEntityDirectoriesHaveMetaFiles(RecursiveDirectoryIterator $iterator, string $projectRootPath, string $scanRoot): void
    {
        $directoryWalker = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($directoryWalker as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isDir()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            if ($absolutePath === $scanRoot) {
                continue;
            }

            if (!is_file($absolutePath . '/_meta.yml')) {
                $relativePath = ltrim(substr($absolutePath, strlen(rtrim($projectRootPath, '/'))), '/');
                throw new InvalidArgumentException("Entity directory [{$relativePath}] must contain [_meta.yml]");
            }
        }
    }
}
