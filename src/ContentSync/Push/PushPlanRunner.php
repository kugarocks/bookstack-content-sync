<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushPlanRunner
{
    public function __construct(
        protected PushPlanPreparer $planPreparer,
        protected LocalSnapshotProjector $localSnapshotProjector,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null): PushRunResult
    {
        $preparation = $this->planPreparer->prepare($projectRootPath, $progress);
        $state = $preparation->state;
        $projectedSnapshotNodes = $this->localSnapshotProjector->projectPreviewSnapshot($state->localNodes, $state->config->contentPath);

        return new PushRunResult(
            plan: $preparation->plan,
            localSnapshotChanges: $this->localSnapshotProjector->diff($state->snapshotNodes, $projectedSnapshotNodes),
        );
    }
}
