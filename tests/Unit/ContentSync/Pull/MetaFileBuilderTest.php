<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class MetaFileBuilderTest extends TestCase
{
    public function test_builds_meta_yaml_with_required_fields()
    {
        $builder = new MetaFileBuilder(new TagNormalizer());
        $node = PullNodeFactory::node(NodeType::Book, [
            'entityId' => 12,
            'name' => '2026',
            'slug' => '2026',
            'description' => '',
            'tags' => [
                PullNodeFactory::tag('2026'),
                PullNodeFactory::tag('series', 'neovim'),
            ],
        ]);

        $contents = $builder->build($node);

        $this->assertStringContainsString('type: "book"', $contents);
        $this->assertStringContainsString('title: "2026"', $contents);
        $this->assertStringContainsString('slug: "2026"', $contents);
        $this->assertStringContainsString('desc: ""', $contents);
        $this->assertStringContainsString('entity_id: 12', $contents);
        $this->assertStringContainsString('- "2026"', $contents);
        $this->assertStringContainsString('- "series:neovim"', $contents);
    }

    public function test_outputs_empty_tags_as_array()
    {
        $builder = new MetaFileBuilder(new TagNormalizer());
        $node = PullNodeFactory::node(NodeType::Chapter, ['tags' => []]);

        $contents = $builder->build($node);

        $this->assertStringContainsString('tags: []', $contents);
    }
}
