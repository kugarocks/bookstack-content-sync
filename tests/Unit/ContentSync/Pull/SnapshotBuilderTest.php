<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\PageMarkdownCodec;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class SnapshotBuilderTest extends TestCase
{
    public function test_builds_snapshot_node_with_push_compatible_fields()
    {
        $builder = new SnapshotBuilder(new ContentHashBuilder(new TagNormalizer()));
        $node = PullNodeFactory::node(NodeType::Page, [
            'entityId' => 12,
            'name' => 'Quick Start',
            'slug' => 'quick-start',
            'markdown' => 'Body',
        ]);

        $snapshotNode = $builder->build($node, 'content/01-blog/01-2026/01-quick-start.md', 1);

        $this->assertSame(NodeType::Page, $snapshotNode->type);
        $this->assertSame('content/01-blog/01-2026/01-quick-start.md', $snapshotNode->file);
        $this->assertSame(12, $snapshotNode->entityId);
        $this->assertSame(1, $snapshotNode->position);
        $this->assertSame('quick-start', $snapshotNode->slug);
        $this->assertSame('Quick Start', $snapshotNode->name);
        $this->assertNotSame('', $snapshotNode->contentHash);
    }

    public function test_normalizes_page_markdown_line_endings_when_building_hash(): void
    {
        $hashBuilder = new ContentHashBuilder(new TagNormalizer());
        $builder = new SnapshotBuilder($hashBuilder);
        $node = PullNodeFactory::node(NodeType::Page, [
            'entityId' => 12,
            'name' => 'Quick Start',
            'slug' => 'quick-start',
            'markdown' => "Line 1\r\nLine 2\rLine 3\n",
        ]);

        $snapshotNode = $builder->build($node, 'content/01-blog/01-2026/01-quick-start.md', 1);
        $expectedHash = $hashBuilder->build(new ContentHashData(
            type: NodeType::Page,
            name: 'Quick Start',
            slug: 'quick-start',
            markdown: "Line 1\nLine 2\nLine 3\n",
            tags: [],
        ));

        $this->assertSame($expectedHash, $snapshotNode->contentHash);
    }

    public function test_decodes_reserved_empty_page_placeholder_when_building_hash(): void
    {
        $hashBuilder = new ContentHashBuilder(new TagNormalizer());
        $builder = new SnapshotBuilder($hashBuilder);
        $node = PullNodeFactory::node(NodeType::Page, [
            'entityId' => 12,
            'name' => 'Quick Start',
            'slug' => 'quick-start',
            'markdown' => PageMarkdownCodec::EMPTY_PAGE_REMOTE_PLACEHOLDER,
        ]);

        $snapshotNode = $builder->build($node, 'content/01-blog/01-2026/01-quick-start.md', 1);
        $expectedHash = $hashBuilder->build(new ContentHashData(
            type: NodeType::Page,
            name: 'Quick Start',
            slug: 'quick-start',
            markdown: '',
            tags: [],
        ));

        $this->assertSame($expectedHash, $snapshotNode->contentHash);
    }
}
