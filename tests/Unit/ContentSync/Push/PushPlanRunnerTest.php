<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlan;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanPreparer;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectState;
use Kugarocks\BookStackContentSync\ContentSync\Push\PushProjectStateLoader;
use Kugarocks\BookStackContentSync\ContentSync\Push\LocalSnapshotProjector;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
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
            new PushPlanPreparer(
                $stateLoader,
                new PushPlanBuilder(new SnapshotMatcher(), new StructureDiffer(), new ContentDiffer()),
            ),
            new LocalSnapshotProjector(),
        );

        $result = $runner->run('/tmp/project');

        $this->assertInstanceOf(PushPlan::class, $result->plan);
        $this->assertCount(1, $result->plan->items());
    }
}
