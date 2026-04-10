<?php

namespace Tests\Unit\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Pull\PullPathBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;

class PullPathBuilderTest extends TestCase
{
    public function test_builds_directory_and_page_paths()
    {
        $builder = new PullPathBuilder();

        $shelfPath = $builder->buildNodePath('content', PullNodeFactory::node(NodeType::Shelf, ['slug' => 'blog']), 1);
        $bookPath = $builder->buildNodePath('content', PullNodeFactory::node(NodeType::Book, ['slug' => '2026']), 2, $shelfPath);
        $pagePath = $builder->buildNodePath('content', PullNodeFactory::node(NodeType::Page, ['slug' => 'quick-start']), 3, $bookPath);

        $this->assertSame('content/01-blog', $shelfPath);
        $this->assertSame('content/01-blog/02-2026', $bookPath);
        $this->assertSame('content/01-blog/02-2026/03-quick-start.md', $pagePath);
    }

    public function test_builds_meta_path_for_directory_nodes()
    {
        $builder = new PullPathBuilder();

        $this->assertSame('content/01-blog/_meta.yml', $builder->buildMetaPath('content/01-blog'));
    }
}
