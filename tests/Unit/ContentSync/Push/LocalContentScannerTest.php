<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use KugaRocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LocalContentScannerTest extends TestCase
{
    public function test_scans_content_tree_with_meta_and_pages(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/content/01-shelf/01-book', 0777, true);
        file_put_contents($root . '/content/01-shelf/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf"
slug: "shelf"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-shelf/01-book/_meta.yml', <<<YAML
type: "book"
title: "Book"
slug: "book"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-shelf/01-book/01-page.md', <<<MD
---
title: "Page"
slug: "page"
tags: []
---

Body
MD);

        $scanner = $this->scanner();

        try {
            $nodes = $scanner->scan($root, 'content');
        } finally {
            $this->deleteDirectory($root);
        }

        $this->assertCount(3, $nodes);
        $this->assertSame('content/01-shelf', $nodes[0]->path);
        $this->assertSame('content/01-shelf/01-book', $nodes[1]->path);
        $this->assertSame('content/01-shelf/01-book/01-page.md', $nodes[2]->path);
    }

    public function test_throws_when_entity_directory_is_missing_meta_file(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/content/01-shelf/01-book', 0777, true);
        file_put_contents($root . '/content/01-shelf/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf"
slug: "shelf"
desc: ""
tags: []
YAML);

        $scanner = $this->scanner();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity directory [content/01-shelf/01-book] must contain [_meta.yml]');

        try {
            $scanner->scan($root, 'content');
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_throws_when_content_directory_does_not_exist(): void
    {
        $root = $this->createTempDirectory();
        $scanner = $this->scanner();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Content directory not found at path [{$root}/content]");

        try {
            $scanner->scan($root, 'content');
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_ignores_non_markdown_files_in_entity_directories(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/content/01-shelf/01-book', 0777, true);
        file_put_contents($root . '/content/01-shelf/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf"
slug: "shelf"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-shelf/01-book/_meta.yml', <<<YAML
type: "book"
title: "Book"
slug: "book"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-shelf/01-book/01-page.md', <<<MD
---
title: "Page"
slug: "page"
tags: []
---

Body
MD);
        file_put_contents($root . '/content/01-shelf/01-book/notes.txt', 'ignored');
        file_put_contents($root . '/content/01-shelf/01-book/image.png', 'ignored');

        $scanner = $this->scanner();

        try {
            $nodes = $scanner->scan($root, 'content');
        } finally {
            $this->deleteDirectory($root);
        }

        $this->assertCount(3, $nodes);
        $this->assertSame([
            'content/01-shelf',
            'content/01-shelf/01-book',
            'content/01-shelf/01-book/01-page.md',
        ], array_map(fn ($node) => $node->path, $nodes));
    }

    public function test_throws_when_meta_file_name_is_incorrect(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/content/01-shelf/01-book', 0777, true);
        file_put_contents($root . '/content/01-shelf/_meta.yml', <<<YAML
type: "shelf"
title: "Shelf"
slug: "shelf"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-shelf/01-book/_meta.yaml', <<<YAML
type: "shelf"
title: "Book"
slug: "book"
desc: ""
tags: []
YAML);

        $scanner = $this->scanner();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity directory [content/01-shelf/01-book] must contain [_meta.yml]');

        try {
            $scanner->scan($root, 'content');
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_loader_reports_top_level_page_as_invalid_project_structure(): void
    {
        $root = $this->createTempDirectory();
        mkdir($root . '/content/01-page-root', 0777, true);
        file_put_contents($root . '/sync.json', json_encode([
            'version' => 1,
            'app_url' => 'https://docs.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'BOOKSTACK_API_TOKEN_ID',
                'token_secret' => 'BOOKSTACK_API_TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/snapshot.json', json_encode([
            'version' => 2,
            'nodes' => [],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-page-root/_meta.yml', <<<YAML
type: "chapter"
title: "Root Chapter"
slug: "root-chapter"
desc: ""
tags: []
YAML);
        file_put_contents($root . '/content/01-page-root/01-root.md', <<<MD
---
title: "Root Page"
slug: "root-page"
tags: []
---

Body
MD);

        $loader = new \KugaRocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader(
            new \KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader(),
            new \KugaRocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader(),
            $this->scanner(),
            new \KugaRocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Top-level local node [content/01-page-root] must be a shelf or book');

        try {
            $loader->load($root);
        } finally {
            $this->deleteDirectory($root);
        }
    }

    protected function scanner(): LocalContentScanner
    {
        return new LocalContentScanner(new LocalFileParser(new ContentHashBuilder(new TagNormalizer())));
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/local-content-scanner-' . bin2hex(random_bytes(8));
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
