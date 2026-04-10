<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

readonly class RemoteTag
{
    public function __construct(
        public ?string $key,
        public string $value,
    ) {
    }
}
