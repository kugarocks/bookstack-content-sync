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
        usort($tags, function (array $a, array $b): int {
            return [$a['name'], $a['value']] <=> [$b['name'], $b['value']];
        });

        return $tags;
    }
}
