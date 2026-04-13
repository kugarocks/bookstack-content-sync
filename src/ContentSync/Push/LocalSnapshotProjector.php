<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;

class LocalSnapshotProjector
{
    /**
     * @param LocalNode[] $localNodes
     * @param array<string, int> $assignedEntityIdsByPath
     * @return SnapshotNode[]
     */
    public function projectPersistedSnapshot(array $localNodes, string $contentPath, array $assignedEntityIdsByPath = []): array
    {
        return $this->projectNodes($localNodes, $contentPath, $assignedEntityIdsByPath, false);
    }

    /**
     * @param LocalNode[] $localNodes
     * @return SnapshotNode[]
     */
    public function projectPreviewSnapshot(array $localNodes, string $contentPath): array
    {
        return $this->projectNodes($localNodes, $contentPath, [], true);
    }

    /**
     * @param LocalNode[] $localNodes
     * @param array<string, int> $assignedEntityIdsByPath
     * @return SnapshotNode[]
     */
    protected function projectNodes(array $localNodes, string $contentPath, array $assignedEntityIdsByPath, bool $allowPartialProjection): array
    {
        $localNodesByPath = [];
        foreach ($localNodes as $localNode) {
            $localNodesByPath[$localNode->path] = $localNode;
        }

        $snapshotNodes = [];
        foreach ($localNodes as $localNode) {
            $entityId = $assignedEntityIdsByPath[$localNode->path] ?? $localNode->entityId;
            if ($entityId === null) {
                if ($allowPartialProjection) {
                    continue;
                }

                throw new InvalidArgumentException("Cannot project snapshot for local node [{$localNode->path}] without entity_id");
            }

            $parentNode = $localNodesByPath[$localNode->parentPath()] ?? null;
            $parentEntityId = $parentNode === null ? null : ($assignedEntityIdsByPath[$parentNode->path] ?? $parentNode->entityId);

            if ($parentNode !== null && $parentEntityId === null && $allowPartialProjection) {
                continue;
            }

            $snapshotNodes[] = new SnapshotNode(
                type: $localNode->type,
                entityId: $entityId,
                file: $localNode->relativePath($contentPath),
                position: $localNode->order,
                contentHash: $localNode->contentHash,
                parent: $parentNode === null || $parentEntityId === null ? null : new SnapshotParent($parentNode->type, $parentEntityId),
                slug: $localNode->slug,
                name: $localNode->name,
            );
        }

        return $snapshotNodes;
    }

    /**
     * @param SnapshotNode[] $beforeNodes
     * @param SnapshotNode[] $afterNodes
     * @return LocalSnapshotChange[]
     */
    public function diff(array $beforeNodes, array $afterNodes): array
    {
        $beforeByIdentity = [];
        foreach ($beforeNodes as $beforeNode) {
            $beforeByIdentity[$beforeNode->identityKey()] = $beforeNode;
        }

        $afterByIdentity = [];
        foreach ($afterNodes as $afterNode) {
            $afterByIdentity[$afterNode->identityKey()] = $afterNode;
        }

        $identityKeys = array_unique(array_merge(array_keys($beforeByIdentity), array_keys($afterByIdentity)));
        sort($identityKeys);

        $changes = [];

        foreach ($identityKeys as $identityKey) {
            $beforeNode = $beforeByIdentity[$identityKey] ?? null;
            $afterNode = $afterByIdentity[$identityKey] ?? null;
            $fieldChanges = $this->fieldChanges($beforeNode, $afterNode);

            if ($fieldChanges === []) {
                continue;
            }

            $referenceNode = $afterNode ?? $beforeNode;
            if ($referenceNode === null) {
                continue;
            }

            $changes[] = new LocalSnapshotChange(
                action: $beforeNode === null ? 'create' : ($afterNode === null ? 'delete' : 'update'),
                type: $referenceNode->type,
                entityId: $referenceNode->entityId,
                path: $afterNode?->file ?? $beforeNode->file,
                name: $referenceNode->name !== '' ? $referenceNode->name : $referenceNode->slug,
                fields: $fieldChanges,
            );
        }

        return $changes;
    }

    /**
     * @return LocalSnapshotFieldChange[]
     */
    protected function fieldChanges(?SnapshotNode $beforeNode, ?SnapshotNode $afterNode): array
    {
        $before = [
            'file' => $beforeNode?->file,
            'position' => $beforeNode?->position,
            'content_hash' => $beforeNode?->contentHash,
            'parent' => $this->parentIdentity($beforeNode?->parent),
            'slug' => $beforeNode?->slug,
            'name' => $beforeNode?->name,
        ];
        $after = [
            'file' => $afterNode?->file,
            'position' => $afterNode?->position,
            'content_hash' => $afterNode?->contentHash,
            'parent' => $this->parentIdentity($afterNode?->parent),
            'slug' => $afterNode?->slug,
            'name' => $afterNode?->name,
        ];

        $changes = [];
        foreach (array_keys($after) as $field) {
            if ($before[$field] === $after[$field]) {
                continue;
            }

            $changes[] = new LocalSnapshotFieldChange(
                field: $field,
                before: $this->stringify($before[$field]),
                after: $this->stringify($after[$field]),
            );
        }

        return $changes;
    }

    protected function parentIdentity(?SnapshotParent $parent): ?string
    {
        if ($parent === null) {
            return null;
        }

        return "{$parent->type->value}:{$parent->entityId}";
    }

    protected function stringify(string|int|null $value): string
    {
        if ($value === null) {
            return '(none)';
        }

        return (string) $value;
    }
}
