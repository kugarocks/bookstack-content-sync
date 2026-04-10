<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\ExportFileKind;
use Kugarocks\BookStackContentSync\ContentSync\Pull\ExportFilePlan;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullResult;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullResultWriter;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PullResultWriterTest extends TestCase
{
    public function test_writes_export_files_and_snapshot_json_to_project_root()
    {
        $projectRoot = $this->createTempDirectory();
        $config = PullNodeFactory::config();
        $result = $this->buildResult();

        (new PullResultWriter(new SnapshotJsonBuilder()))->write($projectRoot, $config, $result);

        $this->assertFileExists($projectRoot . '/content/01-blog/_meta.yml');
        $this->assertFileExists($projectRoot . '/content/01-blog/01-quick-start.md');
        $this->assertFileExists($projectRoot . '/snapshot.json');
        $this->assertSame("type: \"shelf\"\n", file_get_contents($projectRoot . '/content/01-blog/_meta.yml'));
        $this->assertStringContainsString('"type": "page"', file_get_contents($projectRoot . '/snapshot.json'));

        $this->deleteDirectory($projectRoot);
    }

    public function test_throws_if_content_directory_already_contains_files()
    {
        $projectRoot = $this->createTempDirectory();
        $config = PullNodeFactory::config();
        mkdir($projectRoot . '/content', 0777, true);
        file_put_contents($projectRoot . '/content/existing.md', 'old');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target directory must be empty or not exist');

        try {
            (new PullResultWriter(new SnapshotJsonBuilder()))->write($projectRoot, $config, $this->buildResult());
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_throws_if_snapshot_already_exists()
    {
        $projectRoot = $this->createTempDirectory();
        $config = PullNodeFactory::config();
        file_put_contents($projectRoot . '/snapshot.json', '{}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target snapshot must not already exist');

        try {
            (new PullResultWriter(new SnapshotJsonBuilder()))->write($projectRoot, $config, $this->buildResult());
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    public function test_checks_nested_content_path_instead_of_top_level_directory()
    {
        $projectRoot = $this->createTempDirectory();
        $config = PullNodeFactory::config(['contentPath' => 'docs/content']);
        mkdir($projectRoot . '/docs', 0777, true);
        file_put_contents($projectRoot . '/docs/keep.txt', 'safe');

        (new PullResultWriter(new SnapshotJsonBuilder()))->write($projectRoot, $config, $this->buildResult('docs/content'));

        $this->assertFileExists($projectRoot . '/docs/keep.txt');
        $this->assertFileExists($projectRoot . '/docs/content/01-blog/_meta.yml');

        $this->deleteDirectory($projectRoot);
    }

    public function test_throws_for_non_empty_content_directory_even_if_export_file_plans_are_empty()
    {
        $projectRoot = $this->createTempDirectory();
        $config = PullNodeFactory::config();
        mkdir($projectRoot . '/content', 0777, true);
        file_put_contents($projectRoot . '/content/existing.md', 'old');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pull target directory must be empty or not exist');

        try {
            (new PullResultWriter(new SnapshotJsonBuilder()))->write($projectRoot, $config, new PullResult([], []));
        } finally {
            $this->deleteDirectory($projectRoot);
        }
    }

    protected function buildResult(string $contentPath = 'content'): PullResult
    {
        return new PullResult(
            exportFilePlans: [
                new ExportFilePlan($contentPath . '/01-blog/_meta.yml', ExportFileKind::Meta, "type: \"shelf\"\n"),
                new ExportFilePlan($contentPath . '/01-blog/01-quick-start.md', ExportFileKind::Page, "---\ntitle: \"Quick Start\"\n---\n\nBody"),
            ],
            snapshotNodes: [
                PullNodeFactory::snapshotNode(NodeType::Shelf, [
                    'file' => '01-blog',
                    'entityId' => 1,
                    'position' => 1,
                    'slug' => 'blog',
                    'name' => 'Blog',
                    'contentHash' => 'hash-shelf',
                ]),
                PullNodeFactory::snapshotNode(NodeType::Page, [
                    'file' => '01-blog/01-quick-start.md',
                    'entityId' => 2,
                    'position' => 1,
                    'slug' => 'quick-start',
                    'name' => 'Quick Start',
                    'contentHash' => 'hash-page',
                ]),
            ],
        );
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/pull-result-writer-' . bin2hex(random_bytes(8));
        mkdir($path, 0777, true);

        return $path;
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
