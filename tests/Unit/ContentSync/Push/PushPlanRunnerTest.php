<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlan;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushProjectState;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use KugaRocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use KugaRocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;

class PushPlanRunnerTest extends TestCase
{
    public function test_builds_push_plan_from_loaded_project_state()
    {
        $stateLoader = $this->createMock(PushProjectStateLoader::class);
        $state = new PushProjectState(
            config: \Tests\Unit\ContentSync\Pull\PullNodeFactory::config(),
            localNodes: [PushNodeFactory::local(NodeType::Page, ['entityId' => null])],
            snapshotNodes: [],
        );

        $stateLoader->expects($this->once())
            ->method('load')
            ->with('/tmp/project')
            ->willReturn($state);

        $runner = new PushPlanRunner(
            $stateLoader,
            new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
        );

        $plan = $runner->run('/tmp/project');

        $this->assertInstanceOf(PushPlan::class, $plan);
        $this->assertCount(1, $plan->items());
    }
}
