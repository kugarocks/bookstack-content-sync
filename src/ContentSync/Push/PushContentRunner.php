<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushContentRunner
{
    public function __construct(
        protected PushPlanPreparer $planPreparer,
        protected PushPlanExecutor $executor,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null, ?callable $onPlanBuilt = null): PushRunResult
    {
        $preparation = $this->planPreparer->prepare($projectRootPath, $progress);
        $state = $preparation->state;
        $plan = $preparation->plan;
        if ($onPlanBuilt !== null) {
            $onPlanBuilt($plan);
        }
        if ($progress !== null) {
            $progress(PushProgressEvent::stage(PushProgressStage::ExecutingRemoteChanges));
        }
        $localSnapshotChanges = $this->executor->execute(
            $projectRootPath,
            $state->config,
            $state->localNodes,
            $state->snapshotNodes,
            $plan,
            $progress,
        );
        return new PushRunResult($plan, $localSnapshotChanges);
    }
}
