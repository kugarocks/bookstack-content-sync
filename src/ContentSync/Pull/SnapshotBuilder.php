<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use Kugarocks\BookStackContentSync\ContentSync\Shared\PageMarkdownCodec;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class SnapshotBuilder
{
    protected PageMarkdownCodec $pageMarkdownCodec;

    public function __construct(
        protected ContentHashBuilder $contentHashBuilder,
        ?PageMarkdownCodec $pageMarkdownCodec = null,
    ) {
        $this->pageMarkdownCodec = $pageMarkdownCodec ?? new PageMarkdownCodec();
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
                    'name' => $tag->name,
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
        return $this->pageMarkdownCodec->decodeFromRemote($value);
    }
}
