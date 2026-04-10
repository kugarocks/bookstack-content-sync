<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

class PushPlanSummary
{
    /**
     * @return array<string, int>
     */
    public function build(PushPlan $plan): array
    {
        $summary = [];

        foreach ($plan->items() as $item) {
            foreach ($item->actions as $action) {
                $summary[$action->value] = ($summary[$action->value] ?? 0) + 1;
            }
        }

        ksort($summary);

        return $summary;
    }
}
