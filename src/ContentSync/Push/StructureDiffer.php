<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

readonly class StructureDiffer
{
    public function diff(LocalNode $localNode, SnapshotNode $snapshotNode, ?LocalNode $parentNode = null, string $contentPath = 'content'): NodeDiffResult
    {
        $localRelativePath = $localNode->relativePath($contentPath);
        $snapshotFile = $this->normalizeSnapshotFile($snapshotNode->file, $contentPath);
        $parentChanged = $snapshotNode->parent === null
            ? $this->localParentPath($localNode, $contentPath) !== $this->parentPathFromFile($snapshotFile)
            : (($parentNode === null ? null : $parentNode->type->value . ':' . $parentNode->entityId)
                !== ($snapshotNode->parent->type->value . ':' . $snapshotNode->parent->entityId));

        return new NodeDiffResult(
            pathChanged: $localRelativePath !== $snapshotFile,
            parentChanged: $parentChanged,
            orderChanged: $localNode->order !== $snapshotNode->position,
            contentChanged: false,
        );
    }

    protected function normalizeSnapshotFile(string $file, string $contentPath): string
    {
        $contentPath = trim($contentPath, '/');
        $prefix = $contentPath === '' ? '' : $contentPath . '/';

        if ($prefix !== '' && str_starts_with($file, $prefix)) {
            return substr($file, strlen($prefix));
        }

        return ltrim($file, '/');
    }

    protected function parentPathFromFile(string $file): ?string
    {
        $parent = dirname($file);

        return $parent === '.' ? null : $parent;
    }

    protected function localParentPath(LocalNode $localNode, string $contentPath): ?string
    {
        $relative = $localNode->relativePath($contentPath);
        $parent = dirname($relative);

        return $parent === '.' ? null : $parent;
    }
}
