<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

readonly class NodeDiffResult
{
    public function __construct(
        public bool $pathChanged = false,
        public bool $parentChanged = false,
        public bool $orderChanged = false,
        public bool $contentChanged = false,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }
}
