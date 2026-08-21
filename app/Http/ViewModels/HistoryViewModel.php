<?php

namespace App\Http\ViewModels;

use App\Domain\History\ReadModels\ListComparableResearchRuns;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Settings\Enums\MarketKey;
use App\Models\OpportunityScore;
use App\Models\ResearchProject;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HistoryViewModel
{
    public function __construct(private readonly ListComparableResearchRuns $comparableRuns) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(User $user, ?string $anchorPublicId, array $filters = []): array
    {
        $filters = array_merge([
            'q' => '', 'market' => null, 'status' => null, 'min_score' => null,
            'min_confidence' => null, 'date_from' => null, 'date_to' => null,
            'project' => null, 'workspace' => null, 'page' => 1, 'per_page' => 25,
        ], $filters);
        $runs = $this->filteredRuns($user, $filters)
            ->with(['opportunityScores' => fn ($query) => $query
                ->latest('calculated_at')
                ->latest('id')])
            ->with('researchQuery.project')
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) $filters['per_page'], ['*'], 'page', (int) $filters['page']);

        $anchor = $anchorPublicId === null
            ? null
            : $runs->getCollection()->firstWhere('public_id', $anchorPublicId);

        if ($anchorPublicId !== null && $anchor === null) {
            $anchor = ResearchRun::query()
                ->where('user_id', $user->id)
                ->where('public_id', $anchorPublicId)
                ->firstOrFail();
        }

        $candidates = $anchor === null || $anchor->status !== ResearchRunStatus::Completed
            ? []
            : $this->comparableRuns->handle($user, $anchor);

        return [
            'runs' => $runs->getCollection()->map(fn (ResearchRun $run): array => $this->run($run))->values()->all(),
            'selected_anchor' => $anchor?->public_id,
            'candidates' => $candidates,
            'repeat_source' => $anchor !== null && $anchor->status === ResearchRunStatus::Completed && $candidates === []
                ? $this->run($anchor)
                : null,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
            'filter_options' => [
                'markets' => $user->researchRuns()->distinct()->orderBy('market_key')->pluck('market_key')->values()->all(),
                'projects' => $user->researchProjects()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name'])
                    ->map(fn (ResearchProject $project): array => ['public_id' => $project->public_id, 'name' => $project->name])->all(),
                'workspaces' => $user->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name'])
                    ->map(fn (TopicWorkspace $workspace): array => ['public_id' => $workspace->public_id, 'name' => $workspace->name])->all(),
            ],
            'truncated' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ResearchRun>
     */
    private function filteredRuns(User $user, array $filters): Builder
    {
        /** @var Builder<ResearchRun> $query */
        $query = ResearchRun::query()->where('user_id', $user->id);

        if ($filters['q'] !== '') {
            $query->where('query_text', 'like', '%'.addcslashes((string) $filters['q'], '%_\\').'%');
        }
        if ($filters['market'] !== null) {
            $query->where('market_key', $filters['market']);
        }
        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['min_score'] !== null) {
            $query->whereHas('opportunityScores', fn (Builder $scores) => $scores->where('overall_score', '>=', $filters['min_score']));
        }
        if ($filters['min_confidence'] !== null) {
            $query->whereHas('opportunityScores', fn (Builder $scores) => $scores->where('confidence_score', '>=', $filters['min_confidence']));
        }
        if ($filters['date_from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ($filters['project'] !== null) {
            $query->whereHas('researchQuery', fn (Builder $researchQuery) => $researchQuery
                ->where('research_project_id', ResearchProject::query()->where('user_id', $user->id)->where('public_id', $filters['project'])->value('id')));
        }
        if ($filters['workspace'] !== null) {
            $workspaceId = TopicWorkspace::query()->where('user_id', $user->id)->where('public_id', $filters['workspace'])->value('id');
            $query->whereExists(function ($workspaceRuns) use ($workspaceId): void {
                $workspaceRuns->selectRaw('1')
                    ->from('topic_workspace_launches')
                    ->whereColumn('topic_workspace_launches.research_run_id', 'research_runs.id')
                    ->where('topic_workspace_launches.topic_workspace_id', $workspaceId);
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function pair(ResearchRun $before, ResearchRun $after): array
    {
        return [
            'before' => $this->run($before),
            'after' => $this->run($after),
        ];
    }

    /** @return array<string, mixed> */
    private function run(ResearchRun $run): array
    {
        $score = $run->relationLoaded('opportunityScores')
            ? $run->opportunityScores->first()
            : $run->opportunityScores()->latest('calculated_at')->latest('id')->first();

        return [
            'public_id' => $run->public_id,
            'query_text' => $run->query_text,
            'market_key' => $run->market_key,
            'market_name' => MarketKey::tryFrom($run->market_key)?->label() ?? $run->market_key,
            'kind' => $run->kind->value,
            'status' => $run->status->value,
            'attempt_number' => $run->attempt_number,
            'parameters' => $run->parameters,
            'requested_result_count' => $run->requested_result_count,
            'collected_result_count' => $run->collected_result_count,
            'collection_warnings' => $run->collection_warnings ?? [],
            'created_at' => $run->created_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'score' => $this->score($score),
            'can_compare' => $run->status === ResearchRunStatus::Completed,
        ];
    }

    /** @return array<string, mixed>|null */
    private function score(?OpportunityScore $score): ?array
    {
        if ($score === null) {
            return null;
        }

        return [
            'overall_score' => (float) $score->overall_score,
            'confidence_score' => (float) $score->confidence_score,
            'formula_version' => $score->formula_version,
            'calculated_at' => $score->calculated_at->toIso8601String(),
        ];
    }
}
