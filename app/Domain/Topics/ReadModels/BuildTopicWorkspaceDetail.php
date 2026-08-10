<?php

namespace App\Domain\Topics\ReadModels;

use App\Domain\Topics\Services\ResolveTopicEvidence;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\SemanticTopicProfile;
use App\Models\TopicWorkspace;
use App\Models\TopicWorkspaceItem;
use App\Models\TopicWorkspaceLaunch;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchlistItem;

final class BuildTopicWorkspaceDetail
{
    public function __construct(private readonly ResolveTopicEvidence $evidence) {}

    /**
     * @param  array{role:string,type:string}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, TopicWorkspace $workspace, array $filters): array
    {
        $items = $workspace->items()->with('target')->orderBy('sort_position');
        if ($filters['role'] !== 'all') {
            $items->where('evidence_role', $filters['role']);
        }
        if ($filters['type'] !== 'all') {
            $items->where('target_type', $filters['type']);
        }

        $completedRuns = $workspace->items()->where('target_type', 'research_run')
            ->whereHasMorph('target', [ResearchRun::class], fn ($query) => $query->where('user_id', $user->id)->where('market_key', $workspace->market_key)->where('status', 'completed'))
            ->with('target')->get()->map(function (TopicWorkspaceItem $item): ?array {
                $target = $item->target;

                return $target instanceof ResearchRun
                    ? ['public_id' => $target->public_id, 'label' => $target->query_text]
                    : null;
            })->filter()->values()->all();

        return [
            'workspace' => [
                'public_id' => $workspace->public_id, 'name' => $workspace->name, 'description' => $workspace->description,
                'market_key' => $workspace->market_key, 'region_code' => $workspace->region_code,
                'language' => $workspace->relevance_language, 'archived_at' => $workspace->archived_at?->toIso8601String(),
                'project' => $workspace->project ? ['public_id' => $workspace->project->public_id, 'name' => $workspace->project->name] : null,
            ],
            'filters' => $filters,
            'items' => $items->get()->map(fn (TopicWorkspaceItem $item) => $this->item($workspace, $item))->values()->all(),
            'available_evidence' => $this->availableEvidence($user),
            'completed_research_runs' => $completedRuns,
            'launches' => $workspace->launches()->with(['researchRun', 'discoveryRun'])->latest()->limit(20)->get()->map(fn (TopicWorkspaceLaunch $launch) => $this->launch($launch))->all(),
            'projects' => $user->researchProjects()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function item(TopicWorkspace $workspace, TopicWorkspaceItem $item): array
    {
        $target = $item->target;
        $market = $target ? $this->evidence->marketKey($target) : null;

        return [
            'id' => $item->id, 'type' => $item->target_type, 'role' => $item->evidence_role->value,
            'label' => $this->label($target), 'note' => $item->note, 'url' => $this->url($workspace, $target),
            'market_key' => $market,
            'detected_topic_profile' => $this->topicProfile($workspace, $target),
            'cross_market_warning' => $market !== null && $market !== $workspace->market_key
                ? "This evidence was collected for {$market}, not {$workspace->market_key}." : null,
        ];
    }

    /** @return array<string, mixed> */
    private function launch(TopicWorkspaceLaunch $launch): array
    {
        if ($launch->researchRun !== null) {
            return [
                'type' => $launch->launch_type,
                'url' => '/research/runs/'.$launch->researchRun->public_id,
                'label' => $launch->researchRun->query_text,
                'status' => $launch->researchRun->status->value,
                'created_at' => $launch->created_at?->toIso8601String(),
            ];
        }

        return [
            'type' => $launch->launch_type,
            'url' => $launch->discoveryRun === null ? null : '/discover/runs/'.$launch->discoveryRun->public_id,
            'label' => $launch->discoveryRun === null ? 'Unavailable launch' : 'Related candidate discovery',
            'status' => $launch->discoveryRun?->status->value,
            'created_at' => $launch->created_at?->toIso8601String(),
        ];
    }

    private function label(mixed $target): string
    {
        return match (true) {
            $target instanceof Video, $target instanceof Channel => $target->title,
            $target instanceof ResearchQuery, $target instanceof ResearchRun => $target->query_text,
            $target instanceof NicheCandidate => $target->phrase,
            $target instanceof AnalyzerRun => 'Analyzer: '.$target->target_provider_id,
            $target instanceof WatchlistItem => 'Watchlist: '.$target->public_id,
            default => 'Unavailable evidence',
        };
    }

    private function url(TopicWorkspace $workspace, mixed $target): ?string
    {
        return match (true) {
            $target instanceof Video => 'https://www.youtube.com/watch?v='.$target->provider_video_id,
            $target instanceof Channel => 'https://www.youtube.com/channel/'.$target->provider_channel_id,
            $target instanceof ResearchRun => '/research/runs/'.$target->public_id,
            $target instanceof NicheCandidate => '/discover/runs/'.$target->discoveryRun()->value('public_id'),
            $target instanceof AnalyzerRun => '/analyzer/runs/'.$target->public_id.'?return_to='.rawurlencode('/topics/'.$workspace->public_id),
            $target instanceof WatchlistItem => '/watchlist?return_to='.rawurlencode('/topics/'.$workspace->public_id),
            default => null,
        };
    }

    /** @return array{niche: string, language: string, version: string, provenance: string}|null */
    private function topicProfile(TopicWorkspace $workspace, mixed $target): ?array
    {
        $query = SemanticTopicProfile::query()
            ->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')
            ->where('semantic_topic_profiles.user_id', $workspace->user_id)
            ->whereIn('semantic_topic_profiles.status', ['complete', 'partial']);

        match (true) {
            $target instanceof AnalyzerRun => $query->where('analyzer_runs.id', $target->id),
            $target instanceof Video => $query->where('analyzer_runs.target_kind', 'video')->where('analyzer_runs.video_id', $target->id),
            $target instanceof Channel => $query->where('analyzer_runs.target_kind', 'channel')->where('analyzer_runs.channel_id', $target->id),
            default => null,
        };
        if (! $target instanceof AnalyzerRun && ! $target instanceof Video && ! $target instanceof Channel) {
            return null;
        }

        $profile = $query->orderByDesc('semantic_topic_profiles.calculated_at')->first([
            'semantic_topic_profiles.niche_label', 'semantic_topic_profiles.language',
            'semantic_topic_profiles.algorithm_version', 'semantic_topic_profiles.provenance',
        ]);
        if ($profile === null || ! is_string($profile->niche_label)) {
            return null;
        }

        return [
            'niche' => $profile->niche_label,
            'language' => $profile->language,
            'version' => $profile->algorithm_version,
            'provenance' => $profile->provenance,
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function availableEvidence(User $user): array
    {
        $groups = [];
        $groups['research_run'] = $user->researchRuns()->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->public_id, 'label' => $x->query_text, 'market_key' => $x->market_key])->all();
        $groups['research_query'] = $user->researchQueries()->with('market')->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->public_id, 'label' => $x->query_text, 'market_key' => $x->market?->key])->all();
        $groups['niche_candidate'] = NicheCandidate::query()->whereHas('discoveryRun', fn ($q) => $q->where('user_id', $user->id))->with('discoveryRun')->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->public_id, 'label' => $x->phrase, 'market_key' => $x->discoveryRun->market_key])->all();
        $groups['analyzer_run'] = $user->analyzerRuns()->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->public_id, 'label' => ucfirst($x->target_kind).': '.$x->target_provider_id, 'market_key' => null])->all();
        $groups['watchlist_item'] = $user->watchlistItems()->with('target')->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->public_id, 'label' => $x->target?->getAttribute('title') ?? 'Unavailable watched subject', 'market_key' => null])->all();
        $groups['video'] = Video::query()->where(fn ($query) => $query
            ->whereHas('researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
            ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id)))
            ->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->provider_video_id, 'label' => $x->title, 'market_key' => null])->all();
        $groups['channel'] = Channel::query()->where(fn ($query) => $query
            ->whereHas('videos.researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
            ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id)))
            ->latest()->limit(12)->get()->map(fn ($x) => ['reference' => $x->provider_channel_id, 'label' => $x->title, 'market_key' => null])->all();

        return $groups;
    }
}
