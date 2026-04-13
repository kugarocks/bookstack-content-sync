<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;

final readonly class LocalSnapshotChange
{
    /**
     * @param LocalSnapshotFieldChange[] $fields
     */
    public function __construct(
        public string $action,
        public NodeType $type,
        public int $entityId,
        public string $path,
        public string $name,
        public array $fields,
    ) {
    }
}
