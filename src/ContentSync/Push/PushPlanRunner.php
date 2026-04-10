<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushPlanRunner
{
    public function __construct(
        protected PushProjectStateLoader $stateLoader,
        protected PushPlanBuilder $pushPlanBuilder,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null): PushPlan
    {
        if ($progress !== null) {
            $progress('Loading local project state');
        }
        $state = $this->stateLoader->load($projectRootPath);
        if ($progress !== null) {
            $progress('Building push plan');
        }

        return $this->pushPlanBuilder->build($state->localNodes, $state->snapshotNodes, $state->config->contentPath);
    }
}
