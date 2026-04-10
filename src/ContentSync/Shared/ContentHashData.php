<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

readonly class ContentHashData
{
    /**
     * @param array<int, array{name: string, value: string}> $tags
     */
    public function __construct(
        public NodeType $type,
        public string $name,
        public string $slug,
        public string $description = '',
        public string $markdown = '',
        public array $tags = [],
    ) {
    }
}
