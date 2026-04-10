<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

readonly class ContentDiffer
{
    public function diff(LocalNode $localNode, SnapshotNode $snapshotNode): NodeDiffResult
    {
        return new NodeDiffResult(
            contentChanged: $localNode->contentHash !== $snapshotNode->contentHash,
        );
    }
}
