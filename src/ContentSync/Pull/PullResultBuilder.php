<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;

class PullResultBuilder
{
    public function __construct(
        protected PullPathBuilder $pathBuilder,
        protected MetaFileBuilder $metaFileBuilder,
        protected PageFileBuilder $pageFileBuilder,
        protected SnapshotBuilder $snapshotBuilder,
    ) {
    }

    /**
     * @param RemoteNode[] $remoteNodes
     */
    public function build(SyncConfig $config, array $remoteNodes): PullResult
    {
        $exportFilePlans = [];
        $snapshotNodes = [];

        foreach ($this->sortNodes($remoteNodes) as $index => $remoteNode) {
            $this->buildNode(
                config: $config,
                node: $remoteNode,
                position: $index + 1,
                parentPath: '',
                parentSnapshot: null,
                exportFilePlans: $exportFilePlans,
                snapshotNodes: $snapshotNodes,
            );
        }

        return new PullResult($exportFilePlans, $snapshotNodes);
    }

    /**
     * @param ExportFilePlan[] $exportFilePlans
     * @param \KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode[] $snapshotNodes
     */
    protected function buildNode(
        SyncConfig $config,
        RemoteNode $node,
        int $position,
        string $parentPath,
        ?SnapshotParent $parentSnapshot,
        array &$exportFilePlans,
        array &$snapshotNodes,
    ): void {
        $path = $this->pathBuilder->buildNodePath($config->contentPath, $node, $position, $parentPath);
        $snapshotNodes[] = $this->snapshotBuilder->build(
            $node,
            $this->relativeToContentPath($path, $config->contentPath),
            $position,
            $parentSnapshot,
        );

        if ($node->type === NodeType::Page) {
            $exportFilePlans[] = new ExportFilePlan(
                path: $path,
                kind: ExportFileKind::Page,
                contents: $this->pageFileBuilder->build($node),
            );
        } else {
            $exportFilePlans[] = new ExportFilePlan(
                path: $this->pathBuilder->buildMetaPath($path),
                kind: ExportFileKind::Meta,
                contents: $this->metaFileBuilder->build($node),
            );
        }

        foreach ($this->sortNodes($node->children) as $index => $childNode) {
            $this->buildNode(
                $config,
                $childNode,
                $index + 1,
                $path,
                new SnapshotParent($node->type, $node->entityId),
                $exportFilePlans,
                $snapshotNodes,
            );
        }
    }

    /**
     * @param RemoteNode[] $nodes
     * @return RemoteNode[]
     */
    protected function sortNodes(array $nodes): array
    {
        usort($nodes, function (RemoteNode $a, RemoteNode $b): int {
            return [$a->priority, $a->name, $a->entityId] <=> [$b->priority, $b->name, $b->entityId];
        });

        return $nodes;
    }

    protected function relativeToContentPath(string $path, string $contentPath): string
    {
        $contentPath = trim($contentPath, '/');
        $prefix = $contentPath === '' ? '' : $contentPath . '/';

        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return ltrim($path, '/');
    }
}
