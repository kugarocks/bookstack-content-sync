<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Pull\MetaFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\PageFileBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteNode;
use Kugarocks\BookStackContentSync\ContentSync\Pull\RemoteTag;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SnapshotJsonBuilder;
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
    ) {
    }

    /**
     * @param LocalNode[] $localNodes
     * @param array<string, int> $assignedEntityIdsByPath
     * @return SnapshotNode[]
     */
    public function write(string $projectRootPath, string $contentPath, array $localNodes, array $assignedEntityIdsByPath): array
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

        $snapshotNodes = $this->localSnapshotProjector->projectPersistedSnapshot($localNodes, $contentPath, $assignedEntityIdsByPath);

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
