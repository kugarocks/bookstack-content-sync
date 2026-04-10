<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\NodeDiffResult;
use KugaRocks\BookStackContentSync\ContentSync\Push\PlanAction;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlan;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanItem;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanSummary;
use PHPUnit\Framework\TestCase;

class PushPlanSummaryTest extends TestCase
{
    public function test_builds_action_counts_from_push_plan()
    {
        $plan = new PushPlan([
            new PushPlanItem(PushNodeFactory::local(\KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType::Page), null, NodeDiffResult::none(), [PlanAction::Create]),
            new PushPlanItem(null, PushNodeFactory::snapshot(\KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType::Page), NodeDiffResult::none(), [PlanAction::Trash]),
            new PushPlanItem(PushNodeFactory::local(\KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType::Book), PushNodeFactory::snapshot(\KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType::Book), NodeDiffResult::none(), [PlanAction::Rename, PlanAction::Update]),
        ]);

        $summary = (new PushPlanSummary())->build($plan);

        $this->assertSame([
            'create' => 1,
            'rename' => 1,
            'trash' => 1,
            'update' => 1,
        ], $summary);
    }
}
