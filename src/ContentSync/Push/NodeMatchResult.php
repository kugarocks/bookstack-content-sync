<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

readonly class NodeMatchResult
{
    public function __construct(
        public LocalNode $localNode,
        public ?SnapshotNode $snapshotNode,
    ) {
    }

    public function isMatched(): bool
    {
        return $this->snapshotNode !== null;
    }
}
