<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteNode;
use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteTag;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;

class LocalProjectStateWriter
{
    public function __construct(
        protected MetaFileBuilder $metaFileBuilder,
        protected PageFileBuilder $pageFileBuilder,
        protected SnapshotJsonBuilder $snapshotJsonBuilder,
        protected LocalSnapshotProjector $localSnapshotProjector,
        protected ContentHashBuilder $contentHashBuilder,
    ) {
    }

    /**
     * @param LocalNode[] $localNodes
     * @param array<string, int> $assignedEntityIdsByPath
     * @param array<string, string> $resolvedSlugsByPath
     * @return SnapshotNode[]
     */
    public function write(string $projectRootPath, string $contentPath, array $localNodes, array $assignedEntityIdsByPath, array $resolvedSlugsByPath = []): array
    {
        $effectiveLocalNodes = array_map(function (LocalNode $localNode) use ($resolvedSlugsByPath): LocalNode {
            $resolvedSlug = $resolvedSlugsByPath[$localNode->path] ?? $localNode->slug;

            return $localNode->withSlug($resolvedSlug, $this->contentHashBuilder);
        }, $localNodes);

        foreach ($effectiveLocalNodes as $index => $localNode) {
            $originalLocalNode = $localNodes[$index];
            $hasAssignedEntityId = isset($assignedEntityIdsByPath[$localNode->path]);
            $slugChanged = $localNode->slug !== $originalLocalNode->slug;

            if (!$hasAssignedEntityId && !$slugChanged) {
                continue;
            }

            $entityId = $assignedEntityIdsByPath[$localNode->path] ?? $localNode->entityId;
            if ($entityId === null) {
                throw new InvalidArgumentException("Cannot rewrite local node [{$localNode->path}] without entity_id");
            }
            $absolutePath = rtrim($projectRootPath, '/') . '/' . $localNode->path;
            $contents = $localNode->type === NodeType::Page
                ? $this->pageFileBuilder->build($this->toRemoteNode($localNode, $entityId))
                : $this->metaFileBuilder->build($this->toRemoteNode($localNode, $entityId));

            if ($localNode->type !== NodeType::Page) {
                $absolutePath = rtrim($absolutePath, '/') . '/_meta.yml';
            }

            file_put_contents($absolutePath, $contents);
        }

        $snapshotNodes = $this->localSnapshotProjector->projectPersistedSnapshot($effectiveLocalNodes, $contentPath, $assignedEntityIdsByPath);

        file_put_contents(
            rtrim($projectRootPath, '/') . '/snapshot.json',
            $this->snapshotJsonBuilder->build($snapshotNodes),
        );

        return $snapshotNodes;
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
                return new RemoteTag($tag['name'], $tag['value']);
            }, $localNode->tags),
            priority: $localNode->order,
            children: [],
        );
    }
}
