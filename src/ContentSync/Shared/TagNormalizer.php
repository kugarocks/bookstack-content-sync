<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

class TagNormalizer
{
    /**
     * @param array<int, array{name: string, value: string}> $tags
     * @return array<int, array{name: string, value: string}>
     */
    public function normalize(array $tags): array
    {
        return $tags;
    }
}
