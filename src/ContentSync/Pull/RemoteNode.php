<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;

readonly class RemoteNode
{
    /**
     * @param RemoteTag[] $tags
     * @param RemoteNode[] $children
     */
    public function __construct(
        public NodeType $type,
        public int $entityId,
        public string $name,
        public string $slug,
        public string $description = '',
        public string $markdown = '',
        public array $tags = [],
        public int $priority = 0,
        public array $children = [],
    ) {
    }
}
