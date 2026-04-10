<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;

class SnapshotMatcher
{
    /**
     * @param LocalNode[] $localNodes
     * @param SnapshotNode[] $snapshotNodes
     * @return NodeMatchResult[]
     */
    public function match(array $localNodes, array $snapshotNodes): array
    {
        $snapshotMap = $this->buildSnapshotMap($snapshotNodes);
        $results = [];

        foreach ($localNodes as $localNode) {
            $identityKey = $localNode->identityKey();
            $results[] = new NodeMatchResult($localNode, $identityKey ? ($snapshotMap[$identityKey] ?? null) : null);
        }

        return $results;
    }

    /**
     * @param SnapshotNode[] $snapshotNodes
     * @return array<string, SnapshotNode>
     */
    public function buildSnapshotMap(array $snapshotNodes): array
    {
        $snapshotMap = [];

        foreach ($snapshotNodes as $snapshotNode) {
            $identityKey = $snapshotNode->identityKey();
            if (isset($snapshotMap[$identityKey])) {
                throw new InvalidArgumentException("Duplicate snapshot identity [{$identityKey}]");
            }

            $snapshotMap[$identityKey] = $snapshotNode;
        }

        return $snapshotMap;
    }

    /**
     * @param LocalNode[] $localNodes
     * @return array<string, LocalNode>
     */
    public function buildLocalMap(array $localNodes): array
    {
        $localMap = [];

        foreach ($localNodes as $localNode) {
            $identityKey = $localNode->identityKey();
            if ($identityKey === null) {
                continue;
            }

            if (isset($localMap[$identityKey])) {
                throw new InvalidArgumentException("Duplicate local identity [{$identityKey}]");
            }

            $localMap[$identityKey] = $localNode;
        }

        return $localMap;
    }
}
