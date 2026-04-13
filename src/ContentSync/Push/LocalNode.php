<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashData;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;

readonly class LocalNode
{
    /**
     * @param array<int, array{name: string, value: string}> $tags
     */
    public function __construct(
        public NodeType $type,
        public string $path,
        public ?int $entityId,
        public int $order,
        public string $contentHash,
        public string $name = '',
        public string $slug = '',
        public string $description = '',
        public string $markdown = '',
        public array $tags = [],
    ) {
        if ($path === '') {
            throw new InvalidArgumentException('Local node path cannot be empty');
        }
    }

    public function parentPath(): string
    {
        $parentPath = dirname($this->path);

        return $parentPath === '.' ? '' : $parentPath;
    }

    public function identityKey(): ?string
    {
        if ($this->entityId === null) {
            return null;
        }

        return "{$this->type->value}:{$this->entityId}";
    }

    public function relativePath(string $contentPath): string
    {
        $contentPath = trim($contentPath, '/');
        $prefix = $contentPath === '' ? '' : $contentPath . '/';

        if ($prefix !== '' && str_starts_with($this->path, $prefix)) {
            return substr($this->path, strlen($prefix));
        }

        return ltrim($this->path, '/');
    }

    public function withSlug(string $slug, ContentHashBuilder $contentHashBuilder): self
    {
        if ($slug === $this->slug) {
            return $this;
        }

        return new self(
            type: $this->type,
            path: $this->path,
            entityId: $this->entityId,
            order: $this->order,
            contentHash: $contentHashBuilder->build(new ContentHashData(
                type: $this->type,
                name: $this->name,
                slug: $slug,
                description: $this->description,
                markdown: $this->markdown,
                tags: $this->tags,
            )),
            name: $this->name,
            slug: $slug,
            description: $this->description,
            markdown: $this->markdown,
            tags: $this->tags,
        );
    }

}
