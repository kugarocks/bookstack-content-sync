<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class PageFileBuilderTest extends TestCase
{
    public function test_builds_page_front_matter_and_markdown_body()
    {
        $builder = new PageFileBuilder(new TagNormalizer());
        $node = PullNodeFactory::node(NodeType::Page, [
            'entityId' => 99,
            'name' => 'Quick Start',
            'slug' => 'quick-start',
            'markdown' => "# Heading\n\nBody",
            'tags' => [
                PullNodeFactory::tag('2026'),
                PullNodeFactory::tag('series', 'neovim'),
            ],
        ]);

        $contents = $builder->build($node);

        $this->assertStringContainsString("---\ntitle: \"Quick Start\"\nslug: \"quick-start\"\n", $contents);
        $this->assertStringNotContainsString('type: "page"', $contents);
        $this->assertStringContainsString('entity_id: 99', $contents);
        $this->assertStringContainsString('- "2026"', $contents);
        $this->assertStringContainsString('- "series:neovim"', $contents);
        $this->assertStringContainsString("---\n\n# Heading", $contents);
    }

    public function test_outputs_empty_tags_as_array()
    {
        $builder = new PageFileBuilder(new TagNormalizer());
        $node = PullNodeFactory::node(NodeType::Page, ['tags' => [], 'markdown' => 'Body']);

        $contents = $builder->build($node);

        $this->assertStringContainsString('tags: []', $contents);
    }

    public function test_normalizes_page_body_line_endings_to_lf()
    {
        $builder = new PageFileBuilder(new TagNormalizer());
        $node = PullNodeFactory::node(NodeType::Page, [
            'markdown' => "Line 1\r\n\r\nLine 2\rLine 3\n",
        ]);

        $contents = $builder->build($node);

        $this->assertStringContainsString("Line 1\n\nLine 2\nLine 3\n", $contents);
        $this->assertStringNotContainsString("\r", $contents);
    }
}
