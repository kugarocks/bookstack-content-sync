<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;

class LocalFileParser
{
    public function __construct(
        protected ContentHashBuilder $contentHashBuilder,
    ) {
    }

    public function parseMeta(string $contents, string $path): LocalNode
    {
        $context = "meta file [{$path}]";
        $data = $this->parseSimpleYaml($contents, $context);

        try {
            $type = NodeType::from($this->requireString($data, 'type', $context));
        } catch (\ValueError) {
            throw new InvalidArgumentException("Field [type] in {$context} must be one of [shelf, book, chapter]");
        }

        if (!in_array($type, [NodeType::Shelf, NodeType::Book, NodeType::Chapter], true)) {
            throw new InvalidArgumentException("Field [type] in {$context} must be one of [shelf, book, chapter]");
        }

        return new LocalNode(
            type: $type,
            path: dirname($path),
            entityId: $this->optionalInt($data, 'entity_id'),
            order: $this->extractOrder($path),
            contentHash: $this->contentHashBuilder->build(new ContentHashData(
                type: $type,
                name: $this->requireString($data, 'title', $context),
                slug: $this->requireString($data, 'slug', $context),
                description: $this->requireString($data, 'desc', $context),
                tags: $this->parseTags($data, $context),
            )),
            name: $this->requireString($data, 'title', $context),
            slug: $this->requireString($data, 'slug', $context),
            description: $this->requireString($data, 'desc', $context),
            tags: $this->parseTags($data, $context),
        );
    }

    public function parsePage(string $contents, string $path): LocalNode
    {
        if (!preg_match('/^---\n(.*?)\n---\n\n?(.*)$/s', $contents, $matches)) {
            throw new InvalidArgumentException("Page file [{$path}] must start with front matter");
        }

        $context = "page file [{$path}]";
        $data = $this->parseSimpleYaml($matches[1] . "\n", $context);
        $markdown = $matches[2];

        return new LocalNode(
            type: NodeType::Page,
            path: $path,
            entityId: $this->optionalInt($data, 'entity_id'),
            order: $this->extractOrder($path),
            contentHash: $this->contentHashBuilder->build(new ContentHashData(
                type: NodeType::Page,
                name: $this->requireString($data, 'title', $context),
                slug: $this->requireString($data, 'slug', $context),
                markdown: $markdown,
                tags: $this->parseTags($data, $context),
            )),
            name: $this->requireString($data, 'title', $context),
            slug: $this->requireString($data, 'slug', $context),
            markdown: $markdown,
            tags: $this->parseTags($data, $context),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseSimpleYaml(string $contents, string $context): array
    {
        $lines = preg_split('/\r?\n/', rtrim($contents, "\n"));
        $data = [];
        $currentKey = null;
        $currentTagIndex = -1;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([a-z_]+):\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                if ($value === '') {
                    $data[$key] = [];
                    $currentKey = $key;
                    $currentTagIndex = -1;
                    continue;
                }

                $data[$key] = $this->parseScalar($value);
                $currentKey = null;
                $currentTagIndex = -1;
                continue;
            }

            if ($currentKey === 'tags' && preg_match('/^\s{2}-\s+([a-z_]+):\s*(.*)$/', $line, $matches)) {
                $data['tags'][] = [$matches[1] => $this->parseScalar($matches[2])];
                $currentTagIndex = count($data['tags']) - 1;
                continue;
            }

            if ($currentKey === 'tags' && $currentTagIndex >= 0 && preg_match('/^\s{4}([a-z_]+):\s*(.*)$/', $line, $matches)) {
                $data['tags'][$currentTagIndex][$matches[1]] = $this->parseScalar($matches[2]);
                continue;
            }

            throw new InvalidArgumentException("Unsupported YAML format in {$context}");
        }

        return $data;
    }

    protected function parseScalar(string $value): mixed
    {
        $value = trim($value);

        if ($value === '[]') {
            return [];
        }

        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            return str_replace('\"', '"', $matches[1]);
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return intval($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{key: ?string, value: string}>
     */
    protected function parseTags(array $data, string $context): array
    {
        $tags = $data['tags'] ?? [];
        if (!is_array($tags)) {
            throw new InvalidArgumentException("Field [tags] in {$context} must be an array");
        }

        return array_map(function (array $tag) use ($context): array {
            $value = $tag['value'] ?? null;
            if (!is_string($value)) {
                throw new InvalidArgumentException("Tag value in {$context} must be a string");
            }

            $key = $tag['key'] ?? null;
            if ($key !== null && !is_string($key)) {
                throw new InvalidArgumentException("Tag key in {$context} must be a string");
            }

            return [
                'key' => $key,
                'value' => $value,
            ];
        }, $tags);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function requireString(array $data, string $key, string $context): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            throw new InvalidArgumentException("Field [{$key}] in {$context} must be a string");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function optionalInt(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        if (!is_int($data[$key])) {
            throw new InvalidArgumentException("Field [{$key}] must be an integer");
        }

        return $data[$key];
    }

    protected function extractOrder(string $path): int
    {
        $baseName = basename($path);
        if ($baseName === '_meta.yml') {
            $baseName = basename(dirname($path));
        }

        if (!preg_match('/^(\d+)-/', $baseName, $matches)) {
            throw new InvalidArgumentException("Path [{$path}] must start with an order prefix");
        }

        return intval($matches[1]);
    }
}
