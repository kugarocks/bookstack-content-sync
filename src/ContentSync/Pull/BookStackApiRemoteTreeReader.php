<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;

class BookStackApiRemoteTreeReader implements PullRemoteTreeReader
{
    protected SyncConfig $config;
    protected string $tokenId;
    protected string $tokenSecret;

    /**
     * @var array<int, array>
     */
    protected array $bookDetails = [];

    /**
     * @var array<int, array>
     */
    protected array $chapterDetails = [];

    /**
     * @var array<int, array>
     */
    protected array $pageDetails = [];
    protected $progressCallback = null;

    public function __construct(
        protected BookStackApiClient $client,
        protected SyncConfigEnvCredentialResolver $credentialResolver,
    ) {
    }

    public function setProgressCallback(?callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    public function read(SyncConfig $config): array
    {
        $this->config = $config;
        ['tokenId' => $this->tokenId, 'tokenSecret' => $this->tokenSecret] = $this->credentialResolver->resolve($config);
        $this->bookDetails = [];
        $this->chapterDetails = [];
        $this->pageDetails = [];

        $shelfSummaries = $this->client->listAll($config->appUrl, 'shelves', $this->tokenId, $this->tokenSecret);
        $bookSummaries = $this->client->listAll($config->appUrl, 'books', $this->tokenId, $this->tokenSecret);

        $rootNodes = [];
        $shelvedBookIds = [];

        foreach ($shelfSummaries as $shelfIndex => $shelfSummary) {
            $shelfDetail = $this->client->read($config->appUrl, 'shelves/' . $this->requireId($shelfSummary), $this->tokenId, $this->tokenSecret);
            $this->reportProgress(NodeType::Shelf, $this->requireString($shelfDetail, 'name'));
            $childBooks = [];

            foreach (($shelfDetail['books'] ?? []) as $bookIndex => $bookSummary) {
                $bookId = $this->requireId($bookSummary);
                $shelvedBookIds[$bookId] = true;
                $childBooks[] = $this->readBookNode($bookId, $bookIndex);
            }

            $rootNodes[] = $this->mapNode(NodeType::Shelf, $shelfDetail, $shelfIndex, $childBooks);
        }

        foreach ($bookSummaries as $bookIndex => $bookSummary) {
            $bookId = $this->requireId($bookSummary);
            if (isset($shelvedBookIds[$bookId])) {
                continue;
            }

            $rootNodes[] = $this->readBookNode($bookId, $bookIndex);
        }

        return $rootNodes;
    }

    protected function readBookNode(int $bookId, int $position): RemoteNode
    {
        $detail = $this->bookDetails[$bookId] ??= $this->client->read(
            $this->config->appUrl,
            'books/' . $bookId,
            $this->tokenId,
            $this->tokenSecret,
        );
        $this->reportProgress(NodeType::Book, $this->requireString($detail, 'name'));

        $children = [];
        foreach (($detail['contents'] ?? []) as $contentIndex => $contentSummary) {
            $children[] = match ($contentSummary['type'] ?? null) {
                'chapter' => $this->readChapterNode($this->requireId($contentSummary), $contentSummary, $contentIndex),
                'page' => $this->readPageNode($this->requireId($contentSummary), $contentSummary, $contentIndex),
                default => throw new InvalidArgumentException('Unsupported book content type encountered'),
            };
        }

        return $this->mapNode(NodeType::Book, $detail, $position, $children);
    }

    protected function readChapterNode(int $chapterId, array $summary, int $position): RemoteNode
    {
        $detail = $this->chapterDetails[$chapterId] ??= $this->client->read(
            $this->config->appUrl,
            'chapters/' . $chapterId,
            $this->tokenId,
            $this->tokenSecret,
        );
        $this->reportProgress(NodeType::Chapter, $this->requireString($detail, 'name'));

        $pageSummaries = $summary['pages'] ?? $detail['pages'] ?? [];
        $children = [];
        foreach ($pageSummaries as $pageIndex => $pageSummary) {
            $children[] = $this->readPageNode($this->requireId($pageSummary), $pageSummary, $pageIndex);
        }

        return $this->mapNode(NodeType::Chapter, $detail, $this->resolvePriority($summary, $position), $children);
    }

    protected function readPageNode(int $pageId, array $summary, int $position): RemoteNode
    {
        $detail = $this->pageDetails[$pageId] ??= $this->client->read(
            $this->config->appUrl,
            'pages/' . $pageId,
            $this->tokenId,
            $this->tokenSecret,
        );
        $this->reportProgress(NodeType::Page, $this->requireString($detail, 'name'));

        return new RemoteNode(
            type: NodeType::Page,
            entityId: $pageId,
            name: $this->requireString($detail, 'name'),
            slug: $this->requireString($detail, 'slug'),
            description: '',
            markdown: isset($detail['markdown']) && is_string($detail['markdown']) ? $detail['markdown'] : '',
            tags: $this->mapTags($detail['tags'] ?? []),
            priority: $this->resolvePriority($summary, $position),
            children: [],
        );
    }

    /**
     * @param RemoteNode[] $children
     */
    protected function mapNode(NodeType $type, array $data, int $position, array $children): RemoteNode
    {
        return new RemoteNode(
            type: $type,
            entityId: $this->requireId($data),
            name: $this->requireString($data, 'name'),
            slug: $this->requireString($data, 'slug'),
            description: in_array($type, [NodeType::Shelf, NodeType::Book, NodeType::Chapter], true)
                ? (string) ($data['description'] ?? '')
                : '',
            markdown: '',
            tags: $this->mapTags($data['tags'] ?? []),
            priority: $this->resolvePriority($data, $position),
            children: $children,
        );
    }

    /**
     * @return RemoteTag[]
     */
    protected function mapTags(array $tags): array
    {
        $mapped = [];

        foreach ($tags as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            $value = trim((string) ($tag['value'] ?? ''));

            if ($name !== '' && $value !== '') {
                $mapped[] = new RemoteTag($name, $value);
                continue;
            }

            $plainValue = $name !== '' ? $name : $value;
            if ($plainValue !== '') {
                $mapped[] = new RemoteTag(null, $plainValue);
            }
        }

        return $mapped;
    }

    protected function requireId(array $data): int
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidArgumentException('BookStack API response is missing integer [id]');
        }

        return $data['id'];
    }

    protected function requireString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            throw new InvalidArgumentException("BookStack API response is missing non-empty string [{$key}]");
        }

        return $data[$key];
    }

    protected function resolvePriority(array $data, int $fallback): int
    {
        return isset($data['priority']) && is_int($data['priority']) ? $data['priority'] : $fallback;
    }

    protected function reportProgress(NodeType $type, string $name): void
    {
        if ($this->progressCallback === null) {
            return;
        }

        ($this->progressCallback)('Pulling ' . $type->value . ': ' . $name);
    }
}
