<?php

namespace KugaRocks\BookStackContentSync\Console\Commands;

use KugaRocks\BookStackContentSync\ContentSync\Push\PlanAction;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushContentRunner;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanRunner;
use KugaRocks\BookStackContentSync\ContentSync\Push\PushPlanSummary;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Illuminate\Console\Command;
use Throwable;

class PushContentCommand extends Command
{
    protected $signature = 'bookstack:push-content {projectPath} {--e|execute}';
    protected $description = 'Build or execute a push plan for a local content project';

    public function __construct(
        protected PushPlanRunner $runner,
        protected PushContentRunner $pushContentRunner,
        protected PushPlanSummary $summaryBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectPath = (string) $this->argument('projectPath');
        $execute = (bool) $this->option('execute');
        $hasRemoteSemanticChanges = true;
        $progressRenderer = function (string $message, string $tone = 'info') use (&$hasRemoteSemanticChanges): void {
            if (!$hasRemoteSemanticChanges && in_array($message, ['Executing remote changes', 'Writing updated local metadata'], true)) {
                return;
            }

            $this->renderStage($message, $tone);
        };

        try {
            $this->renderStage($execute ? 'Starting push' : 'Starting push plan', 'info');
            $plan = $execute
                ? $this->pushContentRunner->run(
                    $projectPath,
                    $progressRenderer,
                    function ($builtPlan) use (&$hasRemoteSemanticChanges, $execute): void {
                        $hasRemoteSemanticChanges = $this->hasRemoteSemanticChanges($builtPlan);
                        if (!$execute || !$hasRemoteSemanticChanges) {
                            $this->renderPlannedOperations($builtPlan);
                        }
                    },
                )
                : $this->runner->run($projectPath, $progressRenderer);
        } catch (Throwable $exception) {
            $this->renderStage($execute ? 'Push failed.' : 'Push plan failed.', 'error');
            $this->error($exception->getMessage());
            $this->finishOutput();

            return self::FAILURE;
        }

        if (!$execute) {
            $hasRemoteSemanticChanges = $this->hasRemoteSemanticChanges($plan);
            $this->renderPlannedOperations($plan);
        }

        if (!$hasRemoteSemanticChanges) {
            $this->finishOutput();
            return self::SUCCESS;
        }

        $summary = $this->buildDisplaySummary($plan);
        $this->renderSummaryTable(count($plan->items()), $summary);
        $this->newLine();
        $this->renderStage($execute ? 'Push complete.' : 'Push plan complete.', 'success');
        $this->finishOutput();

        return self::SUCCESS;
    }

    protected function renderStage(string $message, string $tone = 'info'): void
    {
        if (in_array($message, ['Executing remote changes', 'Writing updated local metadata'], true)) {
            $this->newLine();
        }

        if ($tone === 'error') {
            $this->newLine();
        }

        if (preg_match('/^(Creating|Updating|Trashing) ([a-z_]+) ([0-9]+\/[0-9]+): (.+)$/', $message, $matches)) {
            $verb = $matches[1];
            $type = $matches[2];
            $progress = $matches[3];
            $path = $matches[4];
            $action = $this->verbToAction($verb);
            $actionLabel = strtoupper($this->displayActionLabel($action));

            $this->line(sprintf(
                '  <fg=%s;options=bold>%s</> <fg=%s;options=bold>%-' . $this->executionActionWidth() . 's</> <fg=cyan>%-' . $this->executionTypeWidth() . 's</> <fg=default>[%s]</> <fg=white>%s</>',
                $this->summaryColor($action),
                $this->actionSymbol($action),
                $this->summaryColor($action),
                $actionLabel,
                $type,
                $progress,
                $path
            ));

            return;
        }

        if (preg_match('/^Syncing shelf membership ([0-9]+\/[0-9]+): (.+)$/', $message, $matches)) {
            $progress = $matches[1];
            $path = $matches[2];
            $action = 'sync_membership';
            $actionLabel = strtoupper($this->displayActionLabel($action));

            $this->line(sprintf(
                '  <fg=%s;options=bold>%s</> <fg=%s;options=bold>%-' . $this->executionActionWidth() . 's</> <fg=cyan>%-' . $this->executionTypeWidth() . 's</> <fg=default>[%s]</> <fg=white>%s</>',
                $this->summaryColor($action),
                $this->actionSymbol($action),
                $this->summaryColor($action),
                $actionLabel,
                'shelf',
                $progress,
                $path
            ));

            return;
        }

        [$icon, $color] = match ($tone) {
            'success' => ['OK', 'green'],
            'error' => ['ERR', 'red'],
            'warn' => ['!', 'yellow'],
            default => ['>', 'cyan'],
        };

        $this->line(sprintf('<fg=%s;options=bold>%s</> %s', $color, $icon, $message));
    }

    protected function renderStat(string $label, int $value): void
    {
        $this->line(sprintf('<fg=default>%s:</> <fg=%s;options=bold>%d</>', $label, $this->summaryColor($label), $value));
    }

    /**
     * @param array<string, int> $summary
     */
    protected function renderSummaryTable(int $planItemCount, array $summary): void
    {
        $rows = [['ITEMS', (string) $planItemCount]];

        $orderedSummary = $summary;
        $skipCount = $orderedSummary['skip'] ?? null;
        unset($orderedSummary['skip']);

        foreach ($orderedSummary as $action => $count) {
            $rows[] = [$this->summaryTableLabel($action), (string) $count];
        }

        if ($skipCount !== null) {
            $rows[] = ['SKIP', (string) $skipCount];
        }

        $this->newLine();
        $this->table(['ACTION', 'COUNT'], $rows);
    }

    protected function renderPlannedOperations($plan): void
    {
        $items = [];
        foreach ($plan->items() as $item) {
            $actions = $this->displayActionsForItem($item);
            if ($actions === [PlanAction::Skip]) {
                continue;
            }

            $items[] = [$item, $actions];
        }

        if ($items === []) {
            $this->newLine();
            $this->line('<fg=green>No remote changes required</>');
            return;
        }

        $this->newLine();
        $this->line('<fg=white;options=bold>Planned changes</>');

        usort($items, function ($a, $b): int {
            $priorityCompare = $this->executionPriority($a[1]) <=> $this->executionPriority($b[1]);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            $aPath = $a[0]->localNode?->path ?? $a[0]->snapshotNode?->file ?? '';
            $bPath = $b[0]->localNode?->path ?? $b[0]->snapshotNode?->file ?? '';

            return $aPath <=> $bPath;
        });

        $groupedItems = [];
        foreach ($items as [$item, $actions]) {
            $groupedItems[$this->formatActionLabel($actions)][] = [$item, $actions];
        }

        foreach ($groupedItems as $label => $group) {
            $primaryAction = $group[0][1][0]->value;
            $this->line(sprintf(
                '  <fg=%s;options=bold>%s</> <fg=%s;options=bold>%s</>',
                $this->summaryColor($primaryAction),
                $this->actionSymbol($primaryAction),
                $this->summaryColor($primaryAction),
                $label
            ));

            foreach ($group as [$item]) {
                $entity = $item->localNode ?? $item->snapshotNode;
                if ($entity === null) {
                    continue;
                }

                $path = $item->localNode?->path ?? $item->snapshotNode?->file ?? 'unknown';
                $type = $entity->type->value;
                $name = $entity->name !== '' ? $entity->name : $entity->slug;

                $this->line(sprintf(
                    '    <fg=cyan>%-' . $this->plannedTypeWidth() . 's</> <fg=white>%s</> <fg=default>(%s)</>',
                    $type,
                    $path,
                    $name
                ));
            }
        }
    }

    /**
     * @param PlanAction[] $actions
     */
    protected function formatActionLabel(array $actions): string
    {
        return strtoupper(implode(' + ', array_map(fn (PlanAction $action) => $this->displayActionLabel($action->value), $actions)));
    }

    protected function verbToAction(string $verb): string
    {
        return match ($verb) {
            'Creating' => 'create',
            'Updating' => 'update',
            'Syncing shelf membership' => 'sync_membership',
            'Trashing' => 'trash',
            default => strtolower($verb),
        };
    }

    protected function actionSymbol(string $action): string
    {
        return match (strtolower($action)) {
            'create' => '+',
            'update' => '~',
            'rename' => '>',
            'move' => '>',
            'sync_membership' => '=',
            'trash' => 'x',
            default => '·',
        };
    }

    protected function summaryColor(string $label): string
    {
        return match (strtolower($label)) {
            'create' => 'green',
            'update' => 'yellow',
            'rename' => 'blue',
            'move' => 'cyan',
            'sync_membership' => 'magenta',
            'trash' => 'red',
            'skip' => 'default',
            'plan items' => 'white',
            default => 'white',
        };
    }

    protected function summaryTableLabel(string $action): string
    {
        return match (strtolower($action)) {
            'sync_membership' => 'MEMBERSHIP',
            default => strtoupper($action),
        };
    }

    protected function displayActionLabel(string $action): string
    {
        return match (strtolower($action)) {
            'sync_membership' => 'MEMBERSHIP',
            default => $action,
        };
    }

    /**
     * @param PlanAction[] $actions
     */
    protected function executionPriority(array $actions): int
    {
        if (in_array(PlanAction::Create, $actions, true)) {
            return 0;
        }

        if (in_array(PlanAction::Rename, $actions, true)
            || in_array(PlanAction::Move, $actions, true)
            || in_array(PlanAction::Update, $actions, true)
        ) {
            return 1;
        }

        if (in_array(PlanAction::SyncMembership, $actions, true)) {
            return 2;
        }

        if (in_array(PlanAction::Trash, $actions, true)) {
            return 3;
        }

        return 4;
    }

    protected function executionActionWidth(): int
    {
        $labels = ['create', 'update', 'sync_membership', 'trash'];
        $maxLength = max(array_map(fn (string $action) => strlen(strtoupper($this->displayActionLabel($action))), $labels));

        return $maxLength + 2;
    }

    protected function executionTypeWidth(): int
    {
        $types = ['shelf', 'book', 'chapter', 'page'];

        return max(array_map('strlen', $types)) + 1;
    }

    protected function plannedTypeWidth(): int
    {
        $types = ['shelf', 'book', 'chapter', 'page'];

        return max(array_map('strlen', $types)) + 1;
    }

    protected function isRemoteSemanticChange($item): bool
    {
        return $this->displayActionsForItem($item) !== [PlanAction::Skip];
    }

    protected function hasRemoteSemanticChanges($plan): bool
    {
        foreach ($plan->items() as $item) {
            if ($this->isRemoteSemanticChange($item)) {
                return true;
            }
        }

        return false;
    }

    protected function buildDisplaySummary($plan): array
    {
        $summary = [];

        foreach ($plan->items() as $item) {
            $actions = $this->displayActionsForItem($item);
            foreach ($actions as $action) {
                $summary[$action->value] = ($summary[$action->value] ?? 0) + 1;
            }
        }

        ksort($summary);

        return $summary;
    }

    protected function displayActionsForItem($item): array
    {
        if ($item->hasAction(PlanAction::Skip)) {
            return [PlanAction::Skip];
        }

        $actions = $item->actions;
        $localNode = $item->localNode;

        if ($localNode?->type === NodeType::Shelf && !$item->diff->contentChanged) {
            $actions = array_values(array_filter($actions, fn (PlanAction $action) => !in_array($action, [PlanAction::Rename, PlanAction::Update], true)));
        }

        if ($actions === [] || $actions === [PlanAction::Rename]) {
            return [PlanAction::Skip];
        }

        return $actions;
    }

    protected function finishOutput(): void
    {
        $this->newLine();
    }
}
