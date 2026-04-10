<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Push\LocalNode;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class PushNodeFactory
{
    public static function local(NodeType $type, array $overrides = []): LocalNode
    {
        return new LocalNode(
            type: $type,
            path: $overrides['path'] ?? self::defaultPath($type),
            entityId: $overrides['entityId'] ?? 1,
            order: $overrides['order'] ?? 10,
            contentHash: $overrides['contentHash'] ?? 'hash-a',
            name: $overrides['name'] ?? 'Title',
            slug: $overrides['slug'] ?? 'slug',
            description: $overrides['description'] ?? '',
            markdown: $overrides['markdown'] ?? '',
            tags: $overrides['tags'] ?? [],
        );
    }

    public static function snapshot(NodeType $type, array $overrides = []): SnapshotNode
    {
        return new SnapshotNode(
            type: $type,
            entityId: $overrides['entityId'] ?? 1,
            file: $overrides['file'] ?? self::defaultPath($type),
            position: $overrides['position'] ?? 10,
            contentHash: $overrides['contentHash'] ?? 'hash-a',
            parent: $overrides['parent'] ?? null,
            slug: $overrides['slug'] ?? 'slug',
            name: $overrides['name'] ?? 'Title',
        );
    }

    protected static function defaultPath(NodeType $type): string
    {
        return match ($type) {
            NodeType::Shelf => '10-shelf',
            NodeType::Book => '10-shelf/10-book',
            NodeType::Chapter => '10-shelf/10-book/10-chapter',
            NodeType::Page => '10-shelf/10-book/10-chapter/10-page.md',
        };
    }
}
