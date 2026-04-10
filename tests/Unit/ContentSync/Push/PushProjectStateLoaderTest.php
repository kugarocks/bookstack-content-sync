<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalContentScanner;
use Kugarocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotFileLoader;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PushProjectStateLoaderTest extends TestCase
{
    public function test_loads_config_snapshot_and_local_nodes_from_project_root()
    {
        $root = sys_get_temp_dir() . '/push-state-loader-' . bin2hex(random_bytes(8));
        mkdir($root . '/content/01-blog', 0777, true);
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
            'nodes' => [[
                'file' => '01-blog',
                'type' => 'shelf',
                'entity_id' => 1,
                'position' => 1,
                'slug' => 'blog',
                'name' => 'Blog',
                'content_hash' => 'hash',
            ]],
        ], JSON_PRETTY_PRINT));
        file_put_contents($root . '/content/01-blog/_meta.yml', <<<YAML
type: "shelf"
title: "Blog"
slug: "blog"
desc: ""
tags: []
entity_id: 1
YAML);

        $loader = new PushProjectStateLoader(
            new SyncConfigLoader(),
            new SnapshotFileLoader(),
            new LocalContentScanner(new \Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
            new ProjectStructureValidator(),
        );

        $state = $loader->load($root);

        $this->assertSame('content', $state->config->contentPath);
        $this->assertCount(1, $state->snapshotNodes);
        $this->assertCount(1, $state->localNodes);

        $this->deleteDirectory($root);
    }

    public function test_throws_when_project_structure_is_invalid()
    {
        $root = sys_get_temp_dir() . '/push-state-loader-invalid-' . bin2hex(random_bytes(8));
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
        file_put_contents($root . '/content/01-page-root/01-root.md', <<<MD
---
title: "Root Page"
slug: "root-page"
tags: []
---

Body
MD);

        $loader = new PushProjectStateLoader(
            new SyncConfigLoader(),
            new SnapshotFileLoader(),
            new LocalContentScanner(new \Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser(new ContentHashBuilder(new TagNormalizer()))),
            new ProjectStructureValidator(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity directory [content/01-page-root] must contain [_meta.yml]');

        try {
            $loader->load($root);
        } finally {
            $this->deleteDirectory($root);
        }
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
