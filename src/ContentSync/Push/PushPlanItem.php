<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

readonly class PushPlanItem
{
    /**
     * @param PlanAction[] $actions
     */
    public function __construct(
        public ?LocalNode $localNode,
        public ?SnapshotNode $snapshotNode,
        public NodeDiffResult $diff,
        public array $actions,
    ) {
    }

    public function hasAction(PlanAction $action): bool
    {
        return in_array($action, $this->actions, true);
    }
}
