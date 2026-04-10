<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;
use KugaRocks\BookStackContentSync\ContentSync\Push\PlanAction;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use KugaRocks\BookStackContentSync\ContentSync\Push\StructureDiffer;

class PushPlanBuilderTest extends TestCase
{
    public function test_builds_skip_action_when_node_is_unchanged()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
        ], [
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
        ]);

        $this->assertCount(1, $plan->items());
        $this->assertSame([PlanAction::Skip], $plan->items()[0]->actions);
    }

    public function test_builds_create_for_unmatched_local_node()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/10-page.md',
                'entityId' => null,
            ]),
        ], []);

        $this->assertCount(1, $plan->items());
        $this->assertSame([PlanAction::Create], $plan->items()[0]->actions);
    }

    public function test_builds_trash_for_snapshot_only_node()
    {
        $plan = $this->builder()->build([], [
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
        ]);

        $this->assertCount(1, $plan->items());
        $this->assertSame([PlanAction::Trash], $plan->items()[0]->actions);
    }

    public function test_builds_rename_for_path_change()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/20-renamed.md',
                'entityId' => 12,
            ]),
        ], [
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
        ]);

        $this->assertSame([PlanAction::Rename], $plan->items()[0]->actions);
    }

    public function test_builds_rename_move_and_update_when_all_apply()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/20-chapter/10-page.md',
                'entityId' => 12,
                'order' => 20,
                'contentHash' => 'hash-b',
            ]),
        ], [
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
        ]);

        $this->assertSame([PlanAction::Rename, PlanAction::Move, PlanAction::Update], $plan->items()[0]->actions);
    }

    public function test_builds_update_for_content_or_order_change_without_move()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Chapter, [
                'path' => '10-book/10-chapter',
                'entityId' => 15,
                'order' => 20,
                'contentHash' => 'hash-b',
            ]),
        ], [
            PushNodeFactory::snapshot(NodeType::Chapter, [
                'file' => '10-book/10-chapter',
                'entityId' => 15,
            ]),
        ]);

        $this->assertSame([PlanAction::Update], $plan->items()[0]->actions);
    }

    public function test_handles_multiple_entity_types()
    {
        $plan = $this->builder()->build([
            PushNodeFactory::local(NodeType::Shelf, ['entityId' => 1]),
            PushNodeFactory::local(NodeType::Book, ['entityId' => 2]),
            PushNodeFactory::local(NodeType::Chapter, ['entityId' => 3, 'contentHash' => 'hash-b']),
            PushNodeFactory::local(NodeType::Page, ['entityId' => null]),
        ], [
            PushNodeFactory::snapshot(NodeType::Shelf, ['entityId' => 1]),
            PushNodeFactory::snapshot(NodeType::Book, ['entityId' => 2]),
            PushNodeFactory::snapshot(NodeType::Chapter, ['entityId' => 3]),
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-shelf/10-book/10-chapter/20-missing.md',
                'entityId' => 4,
                'position' => 20,
                'contentHash' => 'hash-z',
            ]),
        ]);

        $itemsByFirstAction = [];
        foreach ($plan->items() as $item) {
            $itemsByFirstAction[$item->actions[0]->value] = ($itemsByFirstAction[$item->actions[0]->value] ?? 0) + 1;
        }

        $this->assertSame(1, $itemsByFirstAction['sync_membership']);
        $this->assertSame(1, $itemsByFirstAction['skip']);
        $this->assertSame(1, $itemsByFirstAction['update']);
        $this->assertSame(1, $itemsByFirstAction['create']);
        $this->assertSame(1, $itemsByFirstAction['trash']);
    }

    protected function builder(): PushPlanBuilder
    {
        return new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer());
    }
}
