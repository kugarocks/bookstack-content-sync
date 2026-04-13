<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

final readonly class PushRunResult
{
    /**
     * @param LocalSnapshotChange[] $localSnapshotChanges
     */
    public function __construct(
        public PushPlan $plan,
        public array $localSnapshotChanges,
    ) {
    }
}
