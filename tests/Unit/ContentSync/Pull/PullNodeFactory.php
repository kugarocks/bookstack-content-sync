<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteNode;
use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteTag;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfig;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class PullNodeFactory
{
    public static function config(array $overrides = []): SyncConfig
    {
        return new SyncConfig(
            version: $overrides['version'] ?? 1,
            appUrl: $overrides['appUrl'] ?? 'https://www.example.com',
            contentPath: $overrides['contentPath'] ?? 'content',
            tokenIdEnvVar: $overrides['tokenIdEnvVar'] ?? 'TOKEN_ID',
            tokenSecretEnvVar: $overrides['tokenSecretEnvVar'] ?? 'TOKEN_SECRET',
        );
    }

    public static function node(NodeType $type, array $overrides = []): RemoteNode
    {
        return new RemoteNode(
            type: $type,
            entityId: $overrides['entityId'] ?? 1,
            name: $overrides['name'] ?? self::defaultTitle($type),
            slug: $overrides['slug'] ?? self::defaultSlug($type),
            description: $overrides['description'] ?? '',
            markdown: $overrides['markdown'] ?? '',
            tags: $overrides['tags'] ?? [],
            priority: $overrides['priority'] ?? 0,
            children: $overrides['children'] ?? [],
        );
    }

    public static function tag(string $name, string $value = ''): RemoteTag
    {
        return new RemoteTag($name, $value);
    }

    public static function snapshotNode(NodeType $type, array $overrides = []): SnapshotNode
    {
        return new SnapshotNode(
            type: $type,
            entityId: $overrides['entityId'] ?? 1,
            file: $overrides['file'] ?? '01-node',
            position: $overrides['position'] ?? 1,
            contentHash: $overrides['contentHash'] ?? 'hash',
            parent: $overrides['parent'] ?? null,
            slug: $overrides['slug'] ?? self::defaultSlug($type),
            name: $overrides['name'] ?? self::defaultTitle($type),
        );
    }

    protected static function defaultTitle(NodeType $type): string
    {
        return match ($type) {
            NodeType::Shelf => 'Blog',
            NodeType::Book => '2026',
            NodeType::Chapter => 'Neovim',
            NodeType::Page => 'Quick Start',
        };
    }

    protected static function defaultSlug(NodeType $type): string
    {
        return match ($type) {
            NodeType::Shelf => 'blog',
            NodeType::Book => '2026',
            NodeType::Chapter => 'neovim',
            NodeType::Page => 'quick-start',
        };
    }
}
