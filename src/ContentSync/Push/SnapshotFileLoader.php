<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotParent;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;
use JsonException;

class SnapshotFileLoader
{
    /**
     * @return SnapshotNode[]
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Snapshot file not found at path [{$path}]");
        }

        try {
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Invalid JSON in snapshot file [{$path}]", previous: $exception);
        }

        $nodes = $data['nodes'] ?? null;
        if (!is_array($nodes)) {
            throw new InvalidArgumentException('Snapshot file must contain a [nodes] array');
        }

        return array_map(function (array $node): SnapshotNode {
            return new SnapshotNode(
                entityId: $this->requireInt($node, 'entity_id'),
                type: NodeType::from($this->requireString($node, 'type')),
                file: $this->requireString($node, 'file'),
                position: $this->requireInt($node, 'position'),
                contentHash: $this->requireString($node, 'content_hash'),
                parent: $this->parseParent($node['parent'] ?? null),
                slug: $this->requireString($node, 'slug'),
                name: $this->requireString($node, 'name'),
            );
        }, $nodes);
    }

    protected function parseParent(mixed $data): ?SnapshotParent
    {
        if ($data === null) {
            return null;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Snapshot node field [parent] must be an object or null');
        }

        try {
            $type = NodeType::from($this->requireString($data, 'type'));
        } catch (\ValueError) {
            throw new InvalidArgumentException('Snapshot node parent field [type] must be a valid node type');
        }

        return new SnapshotParent(
            type: $type,
            entityId: $this->requireInt($data, 'entity_id'),
        );
    }

    protected function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException("Snapshot node field [{$key}] must be a string");
        }

        return $value;
    }

    protected function requireInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException("Snapshot node field [{$key}] must be an integer");
        }

        return $value;
    }
}
