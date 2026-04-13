<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

final readonly class PushPlanPreparation
{
    public function __construct(
        public PushProjectState $state,
        public PushPlan $plan,
    ) {
    }
}
