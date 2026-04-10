<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

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
