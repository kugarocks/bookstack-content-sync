<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfig;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PushPlanExecutor
{
    public function __construct(
        protected BookStackApiClient $client,
        protected SyncConfigEnvCredentialResolver $credentialResolver,
        protected LocalProjectStateWriter $stateWriter,
        protected LocalSnapshotProjector $localSnapshotProjector,
    ) {
    }

    /**
     * @param LocalNode[] $localNodes
     * @param SnapshotNode[] $snapshotNodes
     * @return LocalSnapshotChange[]
     */
    public function execute(string $projectRootPath, SyncConfig $config, array $localNodes, array $snapshotNodes, PushPlan $plan, ?callable $progress = null): array
    {
        ['tokenId' => $tokenId, 'tokenSecret' => $tokenSecret] = $this->credentialResolver->resolve($config);

        $localNodesByPath = [];
        foreach ($localNodes as $localNode) {
            $localNodesByPath[$localNode->path] = $localNode;
        }

        $assignedEntityIdsByPath = [];

        $createItems = $this->sortCreateItems($plan->itemsForAction(PlanAction::Create));
        foreach ($createItems as $index => $item) {
            $localNode = $item->localNode;
            if ($localNode === null) {
                throw new InvalidArgumentException('Create action requires a local node');
            }

            if ($progress !== null) {
                $progress(PushProgressEvent::create($localNode->type, $index + 1, count($createItems), $localNode->path));
            }

            $response = $this->client->create(
                $config->appUrl,
                $this->collectionPath($localNode->type),
                $tokenId,
                $tokenSecret,
                $this->buildCreatePayload($localNode, $localNodesByPath, $assignedEntityIdsByPath),
            );
            $this->assertResponseMatchesLocalNode($response, $localNode);

            $assignedEntityIdsByPath[$localNode->path] = $this->requireResponseId($response, $localNode->path);
        }

        $updateItems = [];
        foreach ($this->sortUpdateItems($plan->items()) as $item) {
            $localNode = $item->localNode;
            $snapshotNode = $item->snapshotNode;

            if ($localNode === null || $snapshotNode === null) {
                continue;
            }

            $payload = $this->buildUpdatePayload($item, $localNodesByPath, $assignedEntityIdsByPath);
            if ($payload === []) {
                continue;
            }

            $updateItems[] = [
                'item' => $item,
                'payload' => $payload,
            ];
        }

        foreach ($updateItems as $index => $updateItem) {
            /** @var PushPlanItem $item */
            $item = $updateItem['item'];
            $payload = $updateItem['payload'];
            $localNode = $item->localNode;
            $snapshotNode = $item->snapshotNode;
            if ($localNode === null || $snapshotNode === null) {
                continue;
            }

            if ($progress !== null) {
                $progress(PushProgressEvent::update($localNode->type, $index + 1, count($updateItems), $localNode->path));
            }

            $response = $this->client->update(
                $config->appUrl,
                $this->entityPath($snapshotNode->type, $snapshotNode->entityId),
                $tokenId,
                $tokenSecret,
                $payload,
            );
            $this->assertResponseMatchesLocalNode($response, $localNode);
        }

        $shelfItems = $this->sortShelfSyncItems($plan->itemsForAction(PlanAction::SyncMembership));
        foreach ($shelfItems as $index => $item) {
            $shelfNode = $item->localNode;
            if ($shelfNode === null) {
                throw new InvalidArgumentException('Sync membership action requires a local shelf node');
            }

            if ($progress !== null) {
                $progress(PushProgressEvent::syncShelfMembership($index + 1, count($shelfItems), $shelfNode->path));
            }

            $shelfId = $this->resolveEntityId($shelfNode, $assignedEntityIdsByPath);
            $bookIds = [];

            foreach ($this->childBooksForShelf($shelfNode, $localNodes) as $bookNode) {
                $bookIds[] = $this->resolveEntityId($bookNode, $assignedEntityIdsByPath);
            }

            $this->client->update(
                $config->appUrl,
                $this->entityPath(NodeType::Shelf, $shelfId),
                $tokenId,
                $tokenSecret,
                ['books' => $bookIds],
            );
        }

        $trashItems = $this->sortTrashItems($plan->itemsForAction(PlanAction::Trash));
        foreach ($trashItems as $index => $item) {
            $snapshotNode = $item->snapshotNode;
            if ($snapshotNode === null) {
                throw new InvalidArgumentException('Trash action requires a snapshot node');
            }

            if ($progress !== null) {
                $progress(PushProgressEvent::trash($snapshotNode->type, $index + 1, count($trashItems), $snapshotNode->file));
            }

            $this->client->delete(
                $config->appUrl,
                $this->entityPath($snapshotNode->type, $snapshotNode->entityId),
                $tokenId,
                $tokenSecret,
            );
        }

        if ($progress !== null) {
            $progress(PushProgressEvent::stage(PushProgressStage::WritingUpdatedLocalMetadata));
        }
        $writtenSnapshotNodes = $this->stateWriter->write($projectRootPath, $config->contentPath, $localNodes, $assignedEntityIdsByPath);

        return $this->localSnapshotProjector->diff($snapshotNodes, $writtenSnapshotNodes);
    }

    /**
     * @param array<string, LocalNode> $localNodesByPath
     * @param array<string, int> $assignedEntityIdsByPath
     */
    protected function buildCreatePayload(LocalNode $localNode, array $localNodesByPath, array $assignedEntityIdsByPath): array
    {
        return match ($localNode->type) {
            NodeType::Shelf => $this->buildShelfPayload($localNode),
            NodeType::Book => $this->buildBookPayload($localNode),
            NodeType::Chapter => array_merge(
                $this->buildChapterPayload($localNode),
                ['book_id' => $this->resolveBookParentId($localNode, $localNodesByPath, $assignedEntityIdsByPath)],
            ),
            NodeType::Page => array_merge(
                $this->buildPagePayload($localNode),
                $this->resolvePageParentPayload($localNode, $localNodesByPath, $assignedEntityIdsByPath),
            ),
        };
    }

    /**
     * @param array<string, LocalNode> $localNodesByPath
     * @param array<string, int> $assignedEntityIdsByPath
     */
    protected function buildUpdatePayload(PushPlanItem $item, array $localNodesByPath, array $assignedEntityIdsByPath): array
    {
        $localNode = $item->localNode;
        if ($localNode === null) {
            return [];
        }

        if ($item->actions === [PlanAction::Rename]) {
            return [];
        }

        $payload = match ($localNode->type) {
            NodeType::Shelf => $this->buildShelfPayload($localNode),
            NodeType::Book => $this->buildBookPayload($localNode),
            NodeType::Chapter => $this->buildChapterPayload($localNode),
            NodeType::Page => $this->buildPagePayload($localNode),
        };

        if ($item->hasAction(PlanAction::Move)) {
            if ($localNode->type === NodeType::Chapter) {
                $payload['book_id'] = $this->resolveBookParentId($localNode, $localNodesByPath, $assignedEntityIdsByPath);
            }

            if ($localNode->type === NodeType::Page) {
                $payload = array_merge(
                    $payload,
                    $this->resolvePageParentPayload($localNode, $localNodesByPath, $assignedEntityIdsByPath),
                );
            }
        }

        if ($localNode->type === NodeType::Book
            && $item->actions === [PlanAction::Move]
        ) {
            return [];
        }

        if ($localNode->type === NodeType::Shelf
            && $item->actions === [PlanAction::SyncMembership]
        ) {
            return [];
        }

        if ($localNode->type === NodeType::Shelf
            && !$item->diff->contentChanged
        ) {
            return [];
        }

        if ($localNode->type === NodeType::Book
            && $item->actions === [PlanAction::Update]
            && !$item->diff->contentChanged
        ) {
            return [];
        }

        return $payload;
    }

    protected function buildShelfPayload(LocalNode $localNode): array
    {
        return [
            'name' => $localNode->name,
            'slug' => $localNode->slug,
            'description' => $localNode->description,
            'tags' => $this->mapTags($localNode),
        ];
    }

    protected function buildBookPayload(LocalNode $localNode): array
    {
        return [
            'name' => $localNode->name,
            'slug' => $localNode->slug,
            'description' => $localNode->description,
            'tags' => $this->mapTags($localNode),
        ];
    }

    protected function buildChapterPayload(LocalNode $localNode): array
    {
        return [
            'name' => $localNode->name,
            'slug' => $localNode->slug,
            'description' => $localNode->description,
            'tags' => $this->mapTags($localNode),
            'priority' => $localNode->order,
        ];
    }

    protected function buildPagePayload(LocalNode $localNode): array
    {
        return [
            'name' => $localNode->name,
            'slug' => $localNode->slug,
            'markdown' => $localNode->markdown,
            'tags' => $this->mapTags($localNode),
            'priority' => $localNode->order,
        ];
    }

    /**
     * @param array<string, LocalNode> $localNodesByPath
     * @param array<string, int> $assignedEntityIdsByPath
     * @return array{book_id?: int, chapter_id?: int}
     */
    protected function resolvePageParentPayload(LocalNode $localNode, array $localNodesByPath, array $assignedEntityIdsByPath): array
    {
        $parent = $this->requireParentNode($localNode, $localNodesByPath);
        $parentId = $this->resolveEntityId($parent, $assignedEntityIdsByPath);

        return match ($parent->type) {
            NodeType::Book => ['book_id' => $parentId],
            NodeType::Chapter => ['chapter_id' => $parentId],
            default => throw new InvalidArgumentException("Page [{$localNode->path}] must be nested under a book or chapter"),
        };
    }

    /**
     * @param array<string, LocalNode> $localNodesByPath
     * @param array<string, int> $assignedEntityIdsByPath
     */
    protected function resolveBookParentId(LocalNode $localNode, array $localNodesByPath, array $assignedEntityIdsByPath): int
    {
        $parent = $this->requireParentNode($localNode, $localNodesByPath);
        if ($parent->type !== NodeType::Book) {
            throw new InvalidArgumentException("Chapter [{$localNode->path}] must be nested under a book");
        }

        return $this->resolveEntityId($parent, $assignedEntityIdsByPath);
    }

    /**
     * @param array<string, LocalNode> $localNodesByPath
     */
    protected function requireParentNode(LocalNode $localNode, array $localNodesByPath): LocalNode
    {
        $parentPath = $localNode->parentPath();
        $parent = $localNodesByPath[$parentPath] ?? null;

        if ($parent === null) {
            throw new InvalidArgumentException("Parent node [{$parentPath}] not found for local node [{$localNode->path}]");
        }

        return $parent;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    protected function mapTags(LocalNode $localNode): array
    {
        return array_map(function (array $tag): array {
            return [
                'name' => $tag['name'],
                'value' => $tag['value'],
            ];
        }, $localNode->tags);
    }

    protected function collectionPath(NodeType $type): string
    {
        return match ($type) {
            NodeType::Shelf => 'shelves',
            NodeType::Book => 'books',
            NodeType::Chapter => 'chapters',
            NodeType::Page => 'pages',
        };
    }

    protected function entityPath(NodeType $type, int $entityId): string
    {
        return $this->collectionPath($type) . '/' . $entityId;
    }

    protected function requireResponseId(array $response, string $path): int
    {
        $id = Arr::get($response, 'id');
        if (!is_int($id)) {
            throw new InvalidArgumentException("BookStack API response for [{$path}] is missing integer [id]");
        }

        return $id;
    }

    protected function assertResponseMatchesLocalNode(array $response, LocalNode $localNode): void
    {
        $responseSlug = Arr::get($response, 'slug');
        if (!is_string($responseSlug) || trim($responseSlug) === '') {
            throw new InvalidArgumentException("BookStack API response for [{$localNode->path}] is missing string [slug]");
        }

        if ($responseSlug !== $localNode->slug) {
            throw new InvalidArgumentException(
                "Push slug validation failed for [{$localNode->path}]: expected [{$localNode->slug}] but BookStack returned [{$responseSlug}]"
            );
        }
    }

    /**
     * @param array<string, int> $assignedEntityIdsByPath
     */
    protected function resolveEntityId(LocalNode $localNode, array $assignedEntityIdsByPath): int
    {
        $entityId = $assignedEntityIdsByPath[$localNode->path] ?? $localNode->entityId;
        if ($entityId === null) {
            throw new InvalidArgumentException("Local node [{$localNode->path}] is missing entity_id");
        }

        return $entityId;
    }

    /**
     * @param LocalNode[] $localNodes
     * @return LocalNode[]
     */
    protected function sortShelves(array $localNodes): array
    {
        $shelves = array_values(array_filter($localNodes, fn (LocalNode $node) => $node->type === NodeType::Shelf));
        usort($shelves, fn (LocalNode $a, LocalNode $b) => $a->path <=> $b->path);

        return $shelves;
    }

    /**
     * @param PushPlanItem[] $items
     * @return PushPlanItem[]
     */
    protected function sortShelfSyncItems(array $items): array
    {
        usort($items, function (PushPlanItem $a, PushPlanItem $b): int {
            return ($a->localNode?->path ?? '') <=> ($b->localNode?->path ?? '');
        });

        return $items;
    }

    /**
     * @param LocalNode[] $localNodes
     * @return LocalNode[]
     */
    protected function childBooksForShelf(LocalNode $shelfNode, array $localNodes): array
    {
        $books = array_values(array_filter($localNodes, function (LocalNode $node) use ($shelfNode): bool {
            return $node->type === NodeType::Book && $node->parentPath() === $shelfNode->path;
        }));

        usort($books, function (LocalNode $a, LocalNode $b): int {
            if ($a->order !== $b->order) {
                return $a->order <=> $b->order;
            }

            return $a->path <=> $b->path;
        });

        return $books;
    }

    /**
     * @param PushPlanItem[] $items
     * @return PushPlanItem[]
     */
    protected function sortCreateItems(array $items): array
    {
        usort($items, function (PushPlanItem $a, PushPlanItem $b): int {
            $aNode = $a->localNode;
            $bNode = $b->localNode;

            if ($aNode === null || $bNode === null) {
                return 0;
            }

            $depthCompare = $this->pathDepth($aNode->path) <=> $this->pathDepth($bNode->path);
            if ($depthCompare !== 0) {
                return $depthCompare;
            }

            if ($aNode->order !== $bNode->order) {
                return $aNode->order <=> $bNode->order;
            }

            return $aNode->path <=> $bNode->path;
        });

        return $items;
    }

    /**
     * @param PushPlanItem[] $items
     * @return PushPlanItem[]
     */
    protected function sortUpdateItems(array $items): array
    {
        $items = array_values(array_filter($items, fn (PushPlanItem $item) => !$item->hasAction(PlanAction::Create) && !$item->hasAction(PlanAction::Trash) && !$item->hasAction(PlanAction::Skip)));

        usort($items, function (PushPlanItem $a, PushPlanItem $b): int {
            $aNode = $a->localNode;
            $bNode = $b->localNode;

            if ($aNode === null || $bNode === null) {
                return 0;
            }

            $depthCompare = $this->pathDepth($aNode->path) <=> $this->pathDepth($bNode->path);
            if ($depthCompare !== 0) {
                return $depthCompare;
            }

            if ($aNode->order !== $bNode->order) {
                return $aNode->order <=> $bNode->order;
            }

            return $aNode->path <=> $bNode->path;
        });

        return $items;
    }

    /**
     * @param PushPlanItem[] $items
     * @return PushPlanItem[]
     */
    protected function sortTrashItems(array $items): array
    {
        usort($items, function (PushPlanItem $a, PushPlanItem $b): int {
            $aNode = $a->snapshotNode;
            $bNode = $b->snapshotNode;

            if ($aNode === null || $bNode === null) {
                return 0;
            }

            $depthCompare = $this->pathDepth($bNode->file) <=> $this->pathDepth($aNode->file);
            if ($depthCompare !== 0) {
                return $depthCompare;
            }

            return $bNode->file <=> $aNode->file;
        });

        return $items;
    }

    protected function pathDepth(string $path): int
    {
        return count(array_filter(explode('/', trim($path, '/'))));
    }
}
