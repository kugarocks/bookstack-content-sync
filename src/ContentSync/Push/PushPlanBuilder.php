<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;

class PushPlanBuilder
{
    public function __construct(
        protected SnapshotMatcher $matcher,
        protected StructureDiffer $structureDiffer,
        protected ContentDiffer $contentDiffer,
    ) {
    }

    /**
     * @param LocalNode[] $localNodes
     * @param SnapshotNode[] $snapshotNodes
     */
    public function build(array $localNodes, array $snapshotNodes, string $contentPath = 'content'): PushPlan
    {
        $matchResults = $this->matcher->match($localNodes, $snapshotNodes);
        $localMap = $this->matcher->buildLocalMap($localNodes);
        $snapshotMap = $this->matcher->buildSnapshotMap($snapshotNodes);
        $localNodesByPath = [];
        foreach ($localNodes as $localNode) {
            $localNodesByPath[$localNode->path] = $localNode;
        }
        $items = [];

        foreach ($matchResults as $matchResult) {
            if (!$matchResult->isMatched()) {
                $items[] = new PushPlanItem(
                    localNode: $matchResult->localNode,
                    snapshotNode: null,
                    diff: NodeDiffResult::none(),
                    actions: [PlanAction::Create],
                );
                continue;
            }

            $parentNode = $localNodesByPath[$matchResult->localNode->parentPath()] ?? null;
            $structureDiff = $this->structureDiffer->diff(
                $matchResult->localNode,
                $matchResult->snapshotNode,
                $parentNode,
                $contentPath,
            );
            $contentDiff = $this->contentDiffer->diff($matchResult->localNode, $matchResult->snapshotNode);
            $diff = new NodeDiffResult(
                pathChanged: $structureDiff->pathChanged,
                parentChanged: $structureDiff->parentChanged,
                orderChanged: $structureDiff->orderChanged,
                contentChanged: $contentDiff->contentChanged,
            );

            $actions = $this->determineActions($diff);
            if ($matchResult->localNode->type === NodeType::Shelf
                && $this->shelfMembershipChanged($matchResult->localNode, $localNodes, $snapshotMap)
            ) {
                $actions = $this->appendAction($actions, PlanAction::SyncMembership);
            }
            $items[] = new PushPlanItem($matchResult->localNode, $matchResult->snapshotNode, $diff, $actions);
        }

        foreach ($items as $index => $item) {
            if ($item->localNode === null
                || $item->localNode->type !== NodeType::Shelf
                || !$item->hasAction(PlanAction::Create)
                || !$this->shelfHasChildBooks($item->localNode, $localNodes)
            ) {
                continue;
            }

            $items[$index] = new PushPlanItem(
                localNode: $item->localNode,
                snapshotNode: $item->snapshotNode,
                diff: $item->diff,
                actions: $this->appendAction($item->actions, PlanAction::SyncMembership),
            );
        }

        foreach ($snapshotNodes as $snapshotNode) {
            if (isset($localMap[$snapshotNode->identityKey()])) {
                continue;
            }

            $items[] = new PushPlanItem(
                localNode: null,
                snapshotNode: $snapshotNode,
                diff: NodeDiffResult::none(),
                actions: [PlanAction::Trash],
            );
        }

        return new PushPlan($items);
    }

    /**
     * @return PlanAction[]
     */
    protected function determineActions(NodeDiffResult $diff): array
    {
        $actions = [];

        if ($diff->pathChanged) {
            $actions[] = PlanAction::Rename;
        }

        if ($diff->parentChanged) {
            $actions[] = PlanAction::Move;
        }

        if ($diff->contentChanged || $diff->orderChanged) {
            $actions[] = PlanAction::Update;
        }

        if (empty($actions)) {
            $actions[] = PlanAction::Skip;
        }

        return $actions;
    }

    /**
     * @param PlanAction[] $actions
     * @return PlanAction[]
     */
    protected function appendAction(array $actions, PlanAction $action): array
    {
        $actions = array_values(array_filter($actions, fn (PlanAction $existing) => $existing !== PlanAction::Skip));
        if (!in_array($action, $actions, true)) {
            $actions[] = $action;
        }

        return $actions === [] ? [PlanAction::Skip] : $actions;
    }

    /**
     * @param LocalNode[] $localNodes
     * @param array<string, SnapshotNode> $snapshotMap
     */
    protected function shelfMembershipChanged(LocalNode $shelfNode, array $localNodes, array $snapshotMap): bool
    {
        return $this->localShelfBookKeys($shelfNode, $localNodes) !== $this->snapshotShelfBookKeys($shelfNode, $snapshotMap);
    }

    /**
     * @param LocalNode[] $localNodes
     * @return string[]
     */
    protected function localShelfBookKeys(LocalNode $shelfNode, array $localNodes): array
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

        return array_map(fn (LocalNode $book) => $book->identityKey() ?? 'path:' . $book->path, $books);
    }

    /**
     * @param array<string, SnapshotNode> $snapshotMap
     * @return string[]
     */
    protected function snapshotShelfBookKeys(LocalNode $shelfNode, array $snapshotMap): array
    {
        if ($shelfNode->entityId === null) {
            return [];
        }

        $books = array_values(array_filter($snapshotMap, function (SnapshotNode $snapshotNode) use ($shelfNode): bool {
            return $snapshotNode->type === NodeType::Book
                && $snapshotNode->parent?->type === NodeType::Shelf
                && $snapshotNode->parent->entityId === $shelfNode->entityId;
        }));

        usort($books, function (SnapshotNode $a, SnapshotNode $b): int {
            if ($a->position !== $b->position) {
                return $a->position <=> $b->position;
            }

            return $a->file <=> $b->file;
        });

        return array_map(fn (SnapshotNode $book) => $book->identityKey(), $books);
    }

    /**
     * @param LocalNode[] $localNodes
     */
    protected function shelfHasChildBooks(LocalNode $shelfNode, array $localNodes): bool
    {
        foreach ($localNodes as $localNode) {
            if ($localNode->type === NodeType::Book && $localNode->parentPath() === $shelfNode->path) {
                return true;
            }
        }

        return false;
    }
}
