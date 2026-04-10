<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class SnapshotBuilder
{
    public function __construct(
        protected ContentHashBuilder $contentHashBuilder,
    ) {
    }

    public function build(RemoteNode $node, string $file, int $position, ?SnapshotParent $parent = null): SnapshotNode
    {
        $hashData = new ContentHashData(
            type: $node->type,
            name: $node->name,
            slug: $node->slug,
            description: $node->description,
            markdown: $this->normalizeMarkdown($node->markdown),
            tags: array_map(function (RemoteTag $tag): array {
                return [
                    'key' => $tag->key,
                    'value' => $tag->value,
                ];
            }, $node->tags),
        );

        return new SnapshotNode(
            type: $node->type,
            entityId: $node->entityId,
            file: $file,
            position: $position,
            contentHash: $this->contentHashBuilder->build($hashData),
            parent: $parent,
            slug: $node->slug,
            name: $node->name,
        );
    }

    protected function normalizeMarkdown(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
