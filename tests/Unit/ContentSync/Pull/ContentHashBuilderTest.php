<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\PageMarkdownCodec;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class ContentHashBuilderTest extends TestCase
{
    public function test_generates_different_hashes_for_page_markdown_changes()
    {
        $builder = new ContentHashBuilder(new TagNormalizer());
        $before = new ContentHashData(type: NodeType::Page, name: 'Quick Start', slug: 'quick-start', markdown: 'A');
        $after = new ContentHashData(type: NodeType::Page, name: 'Quick Start', slug: 'quick-start', markdown: 'B');

        $this->assertNotSame($builder->build($before), $builder->build($after));
    }

    public function test_generates_different_hashes_for_description_changes()
    {
        $builder = new ContentHashBuilder(new TagNormalizer());
        $before = new ContentHashData(type: NodeType::Book, name: '2026', slug: '2026', description: 'A');
        $after = new ContentHashData(type: NodeType::Book, name: '2026', slug: '2026', description: 'B');

        $this->assertNotSame($builder->build($before), $builder->build($after));
    }

    public function test_treats_reserved_empty_page_placeholder_as_empty_markdown()
    {
        $builder = new ContentHashBuilder(new TagNormalizer());
        $empty = new ContentHashData(type: NodeType::Page, name: 'Quick Start', slug: 'quick-start', markdown: '');
        $placeholder = new ContentHashData(
            type: NodeType::Page,
            name: 'Quick Start',
            slug: 'quick-start',
            markdown: PageMarkdownCodec::EMPTY_PAGE_REMOTE_PLACEHOLDER . "\n"
        );

        $this->assertSame($builder->build($empty), $builder->build($placeholder));
    }

    public function test_generates_different_hashes_for_tag_order_changes()
    {
        $builder = new ContentHashBuilder(new TagNormalizer());
        $before = new ContentHashData(
            type: NodeType::Page,
            name: 'Quick Start',
            slug: 'quick-start',
            markdown: 'Body',
            tags: [
                ['name' => 'blog', 'value' => ''],
                ['name' => '2026', 'value' => ''],
            ],
        );
        $after = new ContentHashData(
            type: NodeType::Page,
            name: 'Quick Start',
            slug: 'quick-start',
            markdown: 'Body',
            tags: [
                ['name' => '2026', 'value' => ''],
                ['name' => 'blog', 'value' => ''],
            ],
        );

        $this->assertNotSame($builder->build($before), $builder->build($after));
    }
}
