<?php

namespace Tests\Unit\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use KugaRocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
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
}
