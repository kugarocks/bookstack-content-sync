<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;

class SnapshotJsonBuilderTest extends TestCase
{
    public function test_builds_snapshot_json_document()
    {
        $builder = new SnapshotJsonBuilder();
        $nodes = [
            PullNodeFactory::snapshotNode(NodeType::Book, [
                'file' => '01-2026',
                'entityId' => 2,
                'position' => 1,
                'slug' => '2026',
                'name' => '2026',
                'contentHash' => 'hash-book',
            ]),
            PullNodeFactory::snapshotNode(NodeType::Page, [
                'file' => '01-2026/01-quick-start.md',
                'entityId' => 10,
                'position' => 1,
                'slug' => 'quick-start',
                'name' => 'Quick Start',
                'contentHash' => 'hash-page',
            ]),
        ];

        $json = $builder->build($nodes);
        $data = json_decode($json, true);

        $this->assertSame(2, $data['version']);
        $this->assertCount(2, $data['nodes']);
        $this->assertSame('book', $data['nodes'][0]['type']);
        $this->assertSame('01-2026/01-quick-start.md', $data['nodes'][1]['file']);
        $this->assertSame('hash-page', $data['nodes'][1]['content_hash']);
    }

    public function test_preserves_unicode_characters_in_snapshot_json()
    {
        $builder = new SnapshotJsonBuilder();
        $nodes = [
            PullNodeFactory::snapshotNode(NodeType::Shelf, [
                'file' => '01-instrument',
                'entityId' => 1,
                'position' => 1,
                'slug' => 'instrument',
                'name' => '乐器',
                'contentHash' => 'hash-shelf',
            ]),
        ];

        $json = $builder->build($nodes);

        $this->assertStringContainsString('"name": "乐器"', $json);
        $this->assertStringNotContainsString('\u4e50\u5668', $json);
    }
}
