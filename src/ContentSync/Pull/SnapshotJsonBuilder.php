<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class SnapshotJsonBuilder
{
    /**
     * @param SnapshotNode[] $nodes
     */
    public function build(array $nodes): string
    {
        $data = [
            'version' => 2,
            'nodes' => array_map(function (SnapshotNode $node): array {
                return [
                    'entity_id' => $node->entityId,
                    'type' => $node->type->value,
                    'name' => $node->name,
                    'slug' => $node->slug,
                    'parent' => $node->parent === null ? null : [
                        'entity_id' => $node->parent->entityId,
                        'type' => $node->parent->type->value,
                    ],
                    'position' => $node->position,
                    'file' => $node->file,
                    'content_hash' => $node->contentHash,
                ];
            }, $nodes),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
