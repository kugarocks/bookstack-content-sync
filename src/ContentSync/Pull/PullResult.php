<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

final class PullResult
{
    /**
     * @param ExportFilePlan[] $exportFilePlans
     * @param SnapshotNode[] $snapshotNodes
     */
    public function __construct(
        public array $exportFilePlans,
        public array $snapshotNodes,
    ) {
    }
}
