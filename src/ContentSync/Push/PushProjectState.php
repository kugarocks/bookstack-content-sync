<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfig;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

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
