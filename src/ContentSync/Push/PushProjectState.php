<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfig;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

readonly class PushProjectState
{
    /**
     * @param LocalNode[] $localNodes
     * @param SnapshotNode[] $snapshotNodes
     */
    public function __construct(
        public SyncConfig $config,
        public array $localNodes,
        public array $snapshotNodes,
    ) {
    }
}
