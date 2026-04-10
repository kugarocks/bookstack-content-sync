<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

final class PushPlan
{
    /**
     * @param PushPlanItem[] $items
     */
    public function __construct(
        protected array $items,
    ) {
    }

    /**
     * @return PushPlanItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function hasActions(): bool
    {
        return !empty($this->items);
    }

    /**
     * @return PushPlanItem[]
     */
    public function itemsForAction(PlanAction $action): array
    {
        return array_values(array_filter($this->items, function (PushPlanItem $item) use ($action) {
            return $item->hasAction($action);
        }));
    }
}
