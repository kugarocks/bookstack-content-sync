<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;

class MetaFileBuilder
{
    public function __construct(
        protected TagNormalizer $tagNormalizer,
    ) {
    }

    public function build(RemoteNode $node): string
    {
        $lines = [
            'type: ' . $this->quote($node->type->value),
            'title: ' . $this->quote($node->name),
            'slug: ' . $this->quote($node->slug),
            'desc: ' . $this->quote($node->description),
            'tags: ' . $this->renderTags($node->tags),
            'entity_id: ' . $node->entityId,
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param RemoteTag[] $tags
     */
    protected function renderTags(array $tags): string
    {
        $tags = $this->tagNormalizer->normalize($this->toTagMaps($tags));
        if (empty($tags)) {
            return '[]';
        }

        $lines = [''];
        foreach ($tags as $tag) {
            if ($tag['key'] !== null) {
                $lines[] = '  - key: ' . $this->quote($tag['key']);
                $lines[] = '    value: ' . $this->quote($tag['value']);
                continue;
            }

            $lines[] = '  - value: ' . $this->quote($tag['value']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param RemoteTag[] $tags
     * @return array<int, array{key: ?string, value: string}>
     */
    protected function toTagMaps(array $tags): array
    {
        return array_map(function (RemoteTag $tag): array {
            return [
                'key' => $tag->key,
                'value' => $tag->value,
            ];
        }, $tags);
    }

    protected function quote(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }
}
