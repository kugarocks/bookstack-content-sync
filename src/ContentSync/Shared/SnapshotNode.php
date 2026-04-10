<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Shared;

use InvalidArgumentException;

readonly class SnapshotNode
{
    public function __construct(
        public NodeType $type,
        public int $entityId,
        public string $file,
        public int $position,
        public string $contentHash,
        public ?SnapshotParent $parent = null,
        public string $slug = '',
        public string $name = '',
    ) {
        if ($file === '') {
            throw new InvalidArgumentException('Snapshot node file cannot be empty');
        }
    }

    public function identityKey(): string
    {
        return "{$this->type->value}:{$this->entityId}";
    }
}
