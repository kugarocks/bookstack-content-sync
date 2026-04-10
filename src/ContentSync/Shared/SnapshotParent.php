<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

readonly class SnapshotParent
{
    public function __construct(
        public NodeType $type,
        public int $entityId,
    ) {
    }
}
