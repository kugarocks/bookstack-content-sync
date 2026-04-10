<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Pull\RemoteNode;
use KugaRocks\BookStackContentSync\ContentSync\Pull\RemoteTag;
use KugaRocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;

class LocalProjectStateWriter
{
    public function __construct(
        protected MetaFileBuilder $metaFileBuilder,
        protected PageFileBuilder $pageFileBuilder,
        protected SnapshotJsonBuilder $snapshotJsonBuilder,
    ) {
    }

    /**
     * @param LocalNode[] $localNodes
     * @param array<string, int> $assignedEntityIdsByPath
     */
    public function write(string $projectRootPath, string $contentPath, array $localNodes, array $assignedEntityIdsByPath): void
    {
        foreach ($localNodes as $localNode) {
            if (!isset($assignedEntityIdsByPath[$localNode->path])) {
                continue;
            }

            $entityId = $assignedEntityIdsByPath[$localNode->path];
            $absolutePath = rtrim($projectRootPath, '/') . '/' . $localNode->path;
            $contents = $localNode->type === NodeType::Page
                ? $this->pageFileBuilder->build($this->toRemoteNode($localNode, $entityId))
                : $this->metaFileBuilder->build($this->toRemoteNode($localNode, $entityId));

            if ($localNode->type !== NodeType::Page) {
                $absolutePath = rtrim($absolutePath, '/') . '/_meta.yml';
            }

            file_put_contents($absolutePath, $contents);
        }

        $localNodesByPath = [];
        foreach ($localNodes as $localNode) {
            $localNodesByPath[$localNode->path] = $localNode;
        }

        $snapshotNodes = array_map(function (LocalNode $localNode) use ($assignedEntityIdsByPath, $localNodesByPath, $contentPath): SnapshotNode {
            $entityId = $assignedEntityIdsByPath[$localNode->path] ?? $localNode->entityId;
            if ($entityId === null) {
                throw new InvalidArgumentException("Cannot write snapshot for local node [{$localNode->path}] without entity_id");
            }

            $parentNode = $localNodesByPath[$localNode->parentPath()] ?? null;
            $parentEntityId = $parentNode === null ? null : ($assignedEntityIdsByPath[$parentNode->path] ?? $parentNode->entityId);

            return new SnapshotNode(
                type: $localNode->type,
                entityId: $entityId,
                file: $localNode->relativePath($contentPath),
                position: $localNode->order,
                contentHash: $localNode->contentHash,
                parent: $parentNode === null || $parentEntityId === null ? null : new SnapshotParent($parentNode->type, $parentEntityId),
                slug: $localNode->slug,
                name: $localNode->name,
            );
        }, $localNodes);

        file_put_contents(
            rtrim($projectRootPath, '/') . '/snapshot.json',
            $this->snapshotJsonBuilder->build($snapshotNodes),
        );
    }

    protected function toRemoteNode(LocalNode $localNode, int $entityId): RemoteNode
    {
        return new RemoteNode(
            type: $localNode->type,
            entityId: $entityId,
            name: $localNode->name,
            slug: $localNode->slug,
            description: $localNode->description,
            markdown: $localNode->markdown,
            tags: array_map(function (array $tag): RemoteTag {
                return new RemoteTag($tag['key'], $tag['value']);
            }, $localNode->tags),
            priority: $localNode->order,
            children: [],
        );
    }
}
