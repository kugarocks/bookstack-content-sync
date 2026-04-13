<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushContentRunner
{
    public function __construct(
        protected PushProjectStateLoader $stateLoader,
        protected PushPlanBuilder $pushPlanBuilder,
        protected PushPlanExecutor $executor,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null, ?callable $onPlanBuilt = null): PushRunResult
    {
        if ($progress !== null) {
            $progress('Loading local project state');
        }
        $state = $this->stateLoader->load($projectRootPath);
        if ($progress !== null) {
            $progress('Building push plan');
        }
        $plan = $this->pushPlanBuilder->build($state->localNodes, $state->snapshotNodes, $state->config->contentPath);
        if ($onPlanBuilt !== null) {
            $onPlanBuilt($plan);
        }
        if ($progress !== null) {
            $progress('Executing remote changes');
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
