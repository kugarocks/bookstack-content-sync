<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushPlanPreparer
{
    public function __construct(
        protected PushProjectStateLoader $stateLoader,
        protected PushPlanBuilder $pushPlanBuilder,
    ) {
    }

    public function prepare(string $projectRootPath, ?callable $progress = null): PushPlanPreparation
    {
        if ($progress !== null) {
            $progress(PushProgressEvent::stage(PushProgressStage::LoadingLocalProjectState));
        }
        $state = $this->stateLoader->load($projectRootPath);
        if ($progress !== null) {
            $progress(PushProgressEvent::stage(PushProgressStage::BuildingPushPlan));
        }

        return new PushPlanPreparation(
            state: $state,
            plan: $this->pushPlanBuilder->build($state->localNodes, $state->snapshotNodes, $state->config->contentPath),
        );
    }
}
