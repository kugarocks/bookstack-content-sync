<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushPlanRunner
{
    public function __construct(
        protected PushProjectStateLoader $stateLoader,
        protected PushPlanBuilder $pushPlanBuilder,
        protected LocalSnapshotProjector $localSnapshotProjector,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null): PushRunResult
    {
        if ($progress !== null) {
            $progress('Loading local project state');
        }
        $state = $this->stateLoader->load($projectRootPath);
        if ($progress !== null) {
            $progress('Building push plan');
        }

        $plan = $this->pushPlanBuilder->build($state->localNodes, $state->snapshotNodes, $state->config->contentPath);
        $projectedSnapshotNodes = $this->localSnapshotProjector->project($state->localNodes, $state->config->contentPath, [], true);

        return new PushRunResult(
            plan: $plan,
            localSnapshotChanges: $this->localSnapshotProjector->diff($state->snapshotNodes, $projectedSnapshotNodes),
        );
    }
}
