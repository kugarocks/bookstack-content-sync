<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

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
