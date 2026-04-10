<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

class TagNormalizer
{
    /**
     * @param array<int, array{key: ?string, value: string}> $tags
     * @return array<int, array{key: ?string, value: string}>
     */
    public function normalize(array $tags): array
    {
        usort($tags, function (array $a, array $b): int {
            return [$a['key'] ?? '', $a['value']] <=> [$b['key'] ?? '', $b['value']];
        });

        return $tags;
    }
}
