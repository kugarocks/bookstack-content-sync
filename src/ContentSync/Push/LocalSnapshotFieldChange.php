<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

final readonly class LocalSnapshotFieldChange
{
    public function __construct(
        public string $field,
        public string $before,
        public string $after,
    ) {
    }
}
