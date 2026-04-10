<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;

class ProjectStructureValidator
{
    /**
     * @param LocalNode[] $localNodes
     */
    public function validate(array $localNodes, string $contentPath): void
    {
        $contentPath = trim($contentPath, '/');
        $nodesByPath = [];
        $identityCounts = [];

        foreach ($localNodes as $localNode) {
            if (isset($nodesByPath[$localNode->path])) {
                throw new InvalidArgumentException("Duplicate local path [{$localNode->path}]");
            }

            $nodesByPath[$localNode->path] = $localNode;

            $identityKey = $localNode->identityKey();
            if ($identityKey !== null) {
                $identityCounts[$identityKey] = ($identityCounts[$identityKey] ?? 0) + 1;
            }
        }

        foreach ($identityCounts as $identityKey => $count) {
            if ($count > 1) {
                throw new InvalidArgumentException("Duplicate local identity [{$identityKey}]");
            }
        }

        foreach ($localNodes as $localNode) {
            $parentPath = $localNode->parentPath();

            if ($this->isTopLevelNode($parentPath, $contentPath)) {
                if (!in_array($localNode->type, [NodeType::Shelf, NodeType::Book], true)) {
                    throw new InvalidArgumentException("Top-level local node [{$localNode->path}] must be a shelf or book");
                }

                continue;
            }

            $parentNode = $nodesByPath[$parentPath] ?? null;
            if ($parentNode === null) {
                throw new InvalidArgumentException("Parent node [{$parentPath}] not found for local node [{$localNode->path}]");
            }

            $this->assertValidParent($localNode, $parentNode);
        }
    }

    protected function isTopLevelNode(string $parentPath, string $contentPath): bool
    {
        if ($parentPath === '') {
            return true;
        }

        return $contentPath !== '' && $parentPath === $contentPath;
    }

    protected function assertValidParent(LocalNode $localNode, LocalNode $parentNode): void
    {
        if ($localNode->type === NodeType::Shelf) {
            throw new InvalidArgumentException("Shelf [{$localNode->path}] cannot be nested under another node");
        }

        if ($localNode->type === NodeType::Book && $parentNode->type !== NodeType::Shelf) {
            throw new InvalidArgumentException("Book [{$localNode->path}] must be nested under a shelf or be a root node");
        }

        if ($localNode->type === NodeType::Chapter && $parentNode->type !== NodeType::Book) {
            throw new InvalidArgumentException("Chapter [{$localNode->path}] must be nested under a book");
        }

        if ($localNode->type === NodeType::Page && !in_array($parentNode->type, [NodeType::Book, NodeType::Chapter], true)) {
            throw new InvalidArgumentException("Page [{$localNode->path}] must be nested under a book or chapter");
        }
    }
}
