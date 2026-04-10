<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\ExportFileKind;
use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullPathBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PullResultBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use PHPUnit\Framework\TestCase;

class PullResultBuilderTest extends TestCase
{
    public function test_builds_export_plans_and_snapshot_nodes_for_tree()
    {
        $builder = $this->builder();
        $config = PullNodeFactory::config();
        $tree = [
            PullNodeFactory::node(NodeType::Shelf, [
                'entityId' => 1,
                'name' => 'Blog',
                'slug' => 'blog',
                'priority' => 0,
                'children' => [
                    PullNodeFactory::node(NodeType::Book, [
                        'entityId' => 2,
                        'name' => '2026',
                        'slug' => '2026',
                        'priority' => 0,
                        'children' => [
                            PullNodeFactory::node(NodeType::Chapter, [
                                'entityId' => 3,
                                'name' => 'Neovim',
                                'slug' => 'neovim',
                                'priority' => 0,
                                'children' => [
                                    PullNodeFactory::node(NodeType::Page, [
                                        'entityId' => 4,
                                        'name' => 'Quick Start',
                                        'slug' => 'quick-start',
                                        'markdown' => 'Body',
                                        'priority' => 0,
                                    ]),
                                ],
                            ]),
                        ],
                    ]),
                ],
            ]),
        ];

        $result = $builder->build($config, $tree);

        $this->assertCount(4, $result->snapshotNodes);
        $this->assertCount(4, $result->exportFilePlans);

        $pagePlan = array_values(array_filter($result->exportFilePlans, fn ($plan) => $plan->kind === ExportFileKind::Page))[0];
        $this->assertSame('content/01-blog/01-2026/01-neovim/01-quick-start.md', $pagePlan->path);

        $metaPaths = array_map(fn ($plan) => $plan->path, array_values(array_filter($result->exportFilePlans, fn ($plan) => $plan->kind === ExportFileKind::Meta)));
        $this->assertContains('content/01-blog/_meta.yml', $metaPaths);
        $this->assertContains('content/01-blog/01-2026/_meta.yml', $metaPaths);
        $this->assertContains('content/01-blog/01-2026/01-neovim/_meta.yml', $metaPaths);
    }

    public function test_sorts_pages_by_priority_under_same_parent()
    {
        $builder = $this->builder();
        $config = PullNodeFactory::config();
        $tree = [
            PullNodeFactory::node(NodeType::Book, [
                'entityId' => 2,
                'name' => '2025',
                'slug' => '2025',
                'children' => [
                    PullNodeFactory::node(NodeType::Page, [
                        'entityId' => 11,
                        'name' => 'Second',
                        'slug' => 'second',
                        'priority' => 5,
                        'markdown' => 'B',
                    ]),
                    PullNodeFactory::node(NodeType::Page, [
                        'entityId' => 10,
                        'name' => 'First',
                        'slug' => 'first',
                        'priority' => 1,
                        'markdown' => 'A',
                    ]),
                ],
            ]),
        ];

        $result = $builder->build($config, $tree);
        $pagePaths = array_map(fn ($plan) => $plan->path, array_values(array_filter($result->exportFilePlans, fn ($plan) => $plan->kind === ExportFileKind::Page)));

        $this->assertSame('content/01-2025/01-first.md', $pagePaths[0]);
        $this->assertSame('content/01-2025/02-second.md', $pagePaths[1]);
    }

    public function test_book_can_contain_pages_without_chapter()
    {
        $builder = $this->builder();
        $config = PullNodeFactory::config();
        $tree = [
            PullNodeFactory::node(NodeType::Book, [
                'entityId' => 2,
                'name' => '2025',
                'slug' => '2025',
                'children' => [
                    PullNodeFactory::node(NodeType::Page, [
                        'entityId' => 10,
                        'name' => 'Quick Start',
                        'slug' => 'quick-start',
                        'priority' => 0,
                        'markdown' => 'A',
                    ]),
                ],
            ]),
        ];

        $result = $builder->build($config, $tree);

        $this->assertCount(2, $result->snapshotNodes);
        $this->assertCount(2, $result->exportFilePlans);
        $this->assertSame('content/01-2025/_meta.yml', $result->exportFilePlans[0]->path);
        $this->assertSame('content/01-2025/01-quick-start.md', $result->exportFilePlans[1]->path);
        $this->assertSame('01-2025/01-quick-start.md', $result->snapshotNodes[1]->file);
    }

    protected function builder(): PullResultBuilder
    {
        $tagNormalizer = new TagNormalizer();

        return new PullResultBuilder(
            new PullPathBuilder(),
            new MetaFileBuilder($tagNormalizer),
            new PageFileBuilder($tagNormalizer),
            new SnapshotBuilder(new ContentHashBuilder($tagNormalizer)),
        );
    }
}
