<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;

class PageFileBuilder
{
    public const EMPTY_PAGE_MARKDOWN_PLACEHOLDER = "---\n";

    public function __construct(
        protected TagNormalizer $tagNormalizer,
    ) {
    }

    public function build(RemoteNode $node): string
    {
        $lines = [
            '---',
            'title: ' . $this->quote($node->name),
            'slug: ' . $this->quote($node->slug),
            'tags: ' . $this->renderTags($node->tags),
            'entity_id: ' . $node->entityId,
            '---',
        ];

        return implode("\n", $lines) . "\n\n" . $this->normalizeMarkdown($node->markdown);
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
            $lines[] = '  - ' . $this->quote($this->formatTag($tag));
        }

        return implode("\n", $lines);
    }

    /**
     * @param RemoteTag[] $tags
     * @return array<int, array{name: string, value: string}>
     */
    protected function toTagMaps(array $tags): array
    {
        return array_map(function (RemoteTag $tag): array {
            return [
                'name' => $tag->name,
                'value' => $tag->value,
            ];
        }, $tags);
    }

    /**
     * @param array{name: string, value: string} $tag
     */
    protected function formatTag(array $tag): string
    {
        if ($tag['value'] === '') {
            return $tag['name'];
        }

        return $tag['name'] . ':' . $tag['value'];
    }

    protected function quote(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    protected function normalizeMarkdown(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        return trim($normalized) === '' ? self::EMPTY_PAGE_MARKDOWN_PLACEHOLDER : $normalized;
    }
}
