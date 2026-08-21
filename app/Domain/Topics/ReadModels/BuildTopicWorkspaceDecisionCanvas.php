<?php

namespace App\Domain\Topics\ReadModels;

use App\Domain\Topics\Services\ResolveTopicEvidence;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\TopicWorkspaceItem;
use Illuminate\Support\Collection;

final class BuildTopicWorkspaceDecisionCanvas
{
    public function __construct(private readonly ResolveTopicEvidence $evidence) {}

    /**
     * @param  Collection<int, TopicWorkspaceItem>  $items
     * @return array<string, mixed>
     */
    public function handle(TopicWorkspace $workspace, Collection $items): array
    {
        $linked = $items->filter(fn (TopicWorkspaceItem $item): bool => $item->target !== null);
        $crossMarket = $linked->filter(fn (TopicWorkspaceItem $item): bool => ($this->evidence->marketKey($item->target) ?? $workspace->market_key) !== $workspace->market_key);
        $completedSameMarketRuns = $linked->filter(fn (TopicWorkspaceItem $item): bool => $item->target instanceof ResearchRun
            && $item->target->status->value === 'completed'
            && $item->target->market_key === $workspace->market_key);
        $unavailable = $items->count() - $linked->count();

        $next = match (true) {
            $workspace->archived_at !== null => [
                'label' => 'Restore workspace to continue',
                'description' => 'This archived workspace is read-only. Its linked evidence remains available.',
                'href' => null,
            ],
            $linked->isEmpty() => [
                'label' => 'Link stored evidence',
                'description' => 'Add an owner-visible canonical record before drawing a decision from this workspace.',
                'href' => '#link-evidence',
            ],
            $completedSameMarketRuns->isEmpty() => [
                'label' => 'Queue a same-market Search',
                'description' => 'A completed same-market Search sample is needed before related candidate discovery is available.',
                'href' => '#launch-search',
            ],
            default => [
                'label' => 'Discover related candidates',
                'description' => 'Use one linked completed same-market Search sample to queue a separate Discovery run.',
                'href' => '#launch-discovery',
            ],
        };

        return [
            'workspace_note' => $workspace->description,
            'coverage' => [
                'linked_count' => $linked->count(),
                'unavailable_count' => $unavailable,
                'cross_market_count' => $crossMarket->count(),
                'same_market_completed_run_count' => $completedSameMarketRuns->count(),
                'roles' => $linked->countBy(fn (TopicWorkspaceItem $item): string => $item->evidence_role->value)->sortKeys()->all(),
            ],
            'warnings' => array_values(array_filter([
                $unavailable > 0 ? "{$unavailable} linked evidence record".($unavailable === 1 ? ' is' : 's are').' unavailable and cannot support this workspace decision.' : null,
                $crossMarket->isNotEmpty() ? "{$crossMarket->count()} linked evidence record".($crossMarket->count() === 1 ? ' is' : 's are').' from another market and need market-context review.' : null,
                $linked->isNotEmpty() && $completedSameMarketRuns->isEmpty() ? 'No completed same-market Search sample is linked yet.' : null,
            ])),
            'next_action' => $next,
        ];
    }
}
