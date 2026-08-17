<?php

namespace App\Domain\Explore\ReadModels;

use App\Models\Channel;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildExploreIndex
{
    private const PER_PAGE = 24;

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters, int $page = 1): array
    {
        $results = match ($filters['entity_type']) {
            'channel' => $this->channels($user, $filters, $page),
            'candidate' => $this->candidates($user, $filters, $page),
            default => $this->videos($user, $filters, $page),
        };

        return [
            'filters' => $filters,
            'results' => $results,
            'counts' => [
                'video' => $this->ownedVideos($user)->count(),
                'channel' => $this->ownedChannels($user)->count(),
                'candidate' => $this->ownedCandidates($user)->count(),
            ],
            'markets' => Market::query()->where('is_enabled', true)->orderBy('sort_order')->get(['key', 'name']),
            'categories' => VideoCategory::query()
                ->where('provider', 'youtube')
                ->where('display_language', 'en')
                ->orderBy('name')
                ->get(['category_id', 'name'])
                ->unique('category_id')
                ->values(),
            'capabilities' => [
                'provider_io_during_browsing' => false,
                'watchlist_available' => true,
                'workspace_available' => true,
            ],
            'workspaces' => $user->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name', 'market_key']),
            'presets' => $user->explorePresets()->latest('updated_at')->get(['public_id', 'name', 'filters']),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function videos(User $user, array $filters, int $returnPage): array
    {
        $query = $this->ownedVideos($user)->select('videos.*')->with('channel');
        $this->filterVideoQuery($query, $user, $filters);
        $this->selectVideoProjection($query, $user);
        $this->sort($query, $filters, 'videos.title');

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $items = collect($page->items())->map(fn (Video $video): array => [
            'entity_type' => 'video',
            'id' => $video->provider_video_id,
            'title' => $video->title,
            'subtitle' => $video->channel->title,
            'thumbnail_url' => $video->thumbnail_url,
            'youtube_url' => "https://www.youtube.com/watch?v={$video->provider_video_id}",
            'market' => $video->getAttribute('market_key'),
            'category' => $video->category_id === null ? null : [
                'id' => $video->category_id,
                'name' => $video->getAttribute('category_name'),
            ],
            'observed_at' => $video->getAttribute('observed_at'),
            'performance' => $this->float($video->getAttribute('performance')),
            'breakout_class' => $video->getAttribute('breakout_class'),
            'subscriber_count' => $this->integer($video->getAttribute('subscriber_count')),
            'score' => $this->float($video->getAttribute('score')),
            'confidence' => $this->float($video->getAttribute('confidence')),
            'favorite' => (bool) $video->getAttribute('is_favorite'),
            'research_status' => $video->getAttribute('research_status'),
            'sources' => $this->sources($video),
            'analyzer_url' => $this->analyzerUrl('video', $video->provider_video_id, $video->getAttribute('analyzer_public_id'), $filters, $returnPage),
            'validate_url' => null,
            'watchlist_public_id' => $video->getAttribute('watchlist_public_id'),
            'partial' => $video->getAttribute('observed_at') === null || $video->getAttribute('performance') === null,
            'detected_topic_profile' => $this->semanticSummary($video),
        ])->values()->all();

        return $this->paginationPayload($page, $items);
    }

    /** @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function channels(User $user, array $filters, int $returnPage): array
    {
        $query = $this->ownedChannels($user)->select('channels.*');
        $this->filterChannelQuery($query, $user, $filters);
        $this->selectChannelProjection($query, $user);
        $this->sort($query, $filters, 'channels.title');

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $items = collect($page->items())->map(fn (Channel $channel): array => [
            'entity_type' => 'channel',
            'id' => $channel->provider_channel_id,
            'title' => $channel->title,
            'subtitle' => $channel->country,
            'thumbnail_url' => $channel->thumbnail_url,
            'youtube_url' => "https://www.youtube.com/channel/{$channel->provider_channel_id}",
            'market' => $channel->getAttribute('market_key'),
            'category' => null,
            'observed_at' => $channel->getAttribute('observed_at'),
            'performance' => $this->float($channel->getAttribute('performance')),
            'breakout_class' => $this->integer($channel->getAttribute('breakout_count')) > 0 ? 'breakout' : null,
            'subscriber_count' => $this->integer($channel->getAttribute('subscriber_count')),
            'score' => $this->float($channel->getAttribute('score')),
            'confidence' => $this->float($channel->getAttribute('confidence')),
            'favorite' => (bool) $channel->getAttribute('is_favorite'),
            'research_status' => $channel->getAttribute('research_status'),
            'sources' => $this->sources($channel),
            'analyzer_url' => $this->analyzerUrl('channel', $channel->provider_channel_id, $channel->getAttribute('analyzer_public_id'), $filters, $returnPage),
            'validate_url' => null,
            'watchlist_public_id' => $channel->getAttribute('watchlist_public_id'),
            'partial' => $channel->getAttribute('observed_at') === null || $channel->getAttribute('subscriber_count') === null,
            'detected_topic_profile' => $this->semanticSummary($channel),
        ])->values()->all();

        return $this->paginationPayload($page, $items);
    }

    /** @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function candidates(User $user, array $filters, int $returnPage): array
    {
        $query = $this->ownedCandidates($user)->select('niche_candidates.*')->with(['discoveryRun', 'validationResearchRun']);
        $this->filterCandidateQuery($query, $user, $filters);
        $query->addSelect([
            DB::raw('niche_candidates.overall_score as score'),
            DB::raw('NULL as performance'),
            'is_favorite' => DB::table('favorites')->selectRaw('1')
                ->whereColumn('favorites.target_id', 'niche_candidates.id')
                ->where('favorites.target_type', 'niche_candidate')->where('favorites.user_id', $user->id)->limit(1),
        ]);
        $this->sort($query, $filters, 'niche_candidates.phrase');
        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $items = collect($page->items())->map(fn (NicheCandidate $candidate): array => [
            'entity_type' => 'candidate',
            'id' => $candidate->public_id,
            'title' => $candidate->phrase,
            'subtitle' => $candidate->summary,
            'thumbnail_url' => null,
            'youtube_url' => null,
            'market' => $candidate->discoveryRun->market_key,
            'category' => null,
            'observed_at' => $candidate->updated_at?->toIso8601String(),
            'performance' => null,
            'breakout_class' => null,
            'subscriber_count' => null,
            'score' => $this->float($candidate->getRawOriginal('overall_score')),
            'confidence' => $this->float($candidate->getRawOriginal('confidence_score')),
            'favorite' => (bool) $candidate->getAttribute('is_favorite'),
            'research_status' => $candidate->status->value,
            'sources' => $candidate->getAttribute('is_favorite') ? ['discovery', 'library'] : ['discovery'],
            'analyzer_url' => null,
            'validate_url' => $candidate->validationResearchRun?->public_id === null
                ? "/discover/candidates/{$candidate->public_id}/validate"
                : '/research/runs/'.$candidate->validationResearchRun->public_id,
            'watchlist_public_id' => null,
            'partial' => $this->float($candidate->getRawOriginal('overall_score')) === null
                || $this->float($candidate->getRawOriginal('confidence_score')) === null,
            'detected_topic_profile' => ($candidate->evidence['inferred_topics'] ?? []) === [] ? null : [
                'niche' => $candidate->evidence['inferred_topics'][0] ?? null,
                'language' => null,
                'version' => $candidate->evidence['inferred_topic_provenance'] ?? null,
            ],
        ])->values()->all();

        return $this->paginationPayload($page, $items);
    }

    /** @return Builder<Video> */
    private function ownedVideos(User $user): Builder
    {
        return Video::query()->where(function (Builder $query) use ($user): void {
            $query->whereHas('researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id))
                ->orWhereHas('analyzerRuns', fn (Builder $runs) => $runs->where('user_id', $user->id))
                ->orWhereExists(fn ($favorites) => $favorites->selectRaw('1')->from('favorites')
                    ->whereColumn('favorites.target_id', 'videos.id')->where('favorites.target_type', 'video')
                    ->where('favorites.user_id', $user->id));
        });
    }

    /** @return Builder<Channel> */
    private function ownedChannels(User $user): Builder
    {
        return Channel::query()->where(function (Builder $query) use ($user): void {
            $query->whereHas('analyzerRuns', fn (Builder $runs) => $runs->where('user_id', $user->id))
                ->orWhereHas('videos.researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id))
                ->orWhereExists(fn ($favorites) => $favorites->selectRaw('1')->from('favorites')
                    ->whereColumn('favorites.target_id', 'channels.id')->where('favorites.target_type', 'channel')
                    ->where('favorites.user_id', $user->id));
        });
    }

    /** @return Builder<NicheCandidate> */
    private function ownedCandidates(User $user): Builder
    {
        return NicheCandidate::query()->whereHas('discoveryRun', fn (Builder $runs) => $runs->where('user_id', $user->id));
    }

    /** @param Builder<Video> $query
     * @param  array<string, mixed>  $filters
     */
    private function filterVideoQuery(Builder $query, User $user, array $filters): void
    {
        $source = $filters['source'];
        if ($source === 'research') {
            $query->whereHas('researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id));
        } elseif ($source === 'analyzer') {
            $query->whereHas('analyzerRuns', fn (Builder $runs) => $runs->where('user_id', $user->id));
        } elseif ($source === 'library') {
            $this->whereFavorite($query, $user, 'videos', 'video');
        } elseif ($source === 'discovery') {
            $query->whereRaw('1 = 0');
        }
        if ($filters['market']) {
            $query->whereHas('researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id)->where('market_key', $filters['market']));
        }
        if ($filters['category']) {
            $query->where('category_id', $filters['category']);
        }
        if ($filters['topic']) {
            $term = '%'.$this->escapeLike($filters['topic']).'%';
            $query->where(fn (Builder $q) => $q->where('videos.title', 'like', $term)
                ->orWhereHas('channel', fn (Builder $c) => $c->where('title', 'like', $term))
                ->orWhereExists(fn ($semantic) => $semantic->selectRaw('1')->from('semantic_classifications')
                    ->join('semantic_topic_profiles', 'semantic_topic_profiles.id', '=', 'semantic_classifications.semantic_topic_profile_id')
                    ->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')
                    ->whereColumn('analyzer_runs.video_id', 'videos.id')
                    ->where('semantic_topic_profiles.user_id', $user->id)
                    ->where('semantic_classifications.label', 'like', $term)));
        }
        if ($filters['breakout']) {
            $query->whereHas('analyzerRuns.videoMetrics', fn (Builder $metrics) => $metrics->where('breakout_class', $filters['breakout'])->whereHas('analyzerRun', fn (Builder $runs) => $runs->where('user_id', $user->id)));
        }
        if ($filters['min_performance'] !== null) {
            $query->whereHas('analyzerRuns.videoMetrics', fn (Builder $metrics) => $metrics->where('lifetime_views_per_day', '>=', $filters['min_performance'])->whereHas('analyzerRun', fn (Builder $runs) => $runs->where('user_id', $user->id)));
        }
        $this->filterChannelSize($query, $user, $filters['channel_size'], 'videos.channel_id');
        $this->filterResearchScore($query, $user, $filters, 'video');
        $this->filterObservedDate($query, $user, $filters, 'video', 'videos.id');
        $this->filterOrganization($query, $user, $filters['organization'], 'videos', 'video');
    }

    /** @param Builder<Channel> $query
     * @param  array<string, mixed>  $filters
     */
    private function filterChannelQuery(Builder $query, User $user, array $filters): void
    {
        if ($filters['source'] === 'research') {
            $query->whereHas('videos.researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id));
        } elseif ($filters['source'] === 'analyzer') {
            $query->whereHas('analyzerRuns', fn (Builder $runs) => $runs->where('user_id', $user->id));
        } elseif ($filters['source'] === 'library') {
            $this->whereFavorite($query, $user, 'channels', 'channel');
        } elseif ($filters['source'] === 'discovery') {
            $query->whereRaw('1 = 0');
        }
        if ($filters['market']) {
            $query->whereHas('videos.researchRuns', fn (Builder $runs) => $runs->where('research_runs.user_id', $user->id)->where('market_key', $filters['market']));
        }
        if ($filters['category']) {
            $query->whereHas('videos', fn (Builder $videos) => $videos->where('category_id', $filters['category']));
        }
        if ($filters['topic']) {
            $term = '%'.$this->escapeLike($filters['topic']).'%';
            $query->where(fn (Builder $q) => $q->where('channels.title', 'like', $term)
                ->orWhereHas('videos', fn (Builder $videos) => $videos->where('title', 'like', $term))
                ->orWhereExists(fn ($semantic) => $semantic->selectRaw('1')->from('semantic_classifications')
                    ->join('semantic_topic_profiles', 'semantic_topic_profiles.id', '=', 'semantic_classifications.semantic_topic_profile_id')
                    ->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')
                    ->whereColumn('analyzer_runs.channel_id', 'channels.id')
                    ->where('semantic_topic_profiles.user_id', $user->id)
                    ->where('semantic_classifications.label', 'like', $term)));
        }
        if ($filters['breakout']) {
            $query->whereHas('analyzerRuns.channelMetrics', function (Builder $metrics) use ($user, $filters): void {
                $metrics->whereHas('analyzerRun', fn (Builder $runs) => $runs->where('user_id', $user->id));
                match ($filters['breakout']) {
                    'breakout' => $metrics->where('breakout_count', '>', 0),
                    'strong' => $metrics->where('strong_count', '>', 0),
                    default => $metrics->where('breakout_count', 0)->where('strong_count', 0),
                };
            });
        }
        if ($filters['min_performance'] !== null) {
            $query->whereHas('analyzerRuns.channelMetrics', fn (Builder $metrics) => $metrics
                ->where('median_views', '>=', $filters['min_performance'])
                ->whereHas('analyzerRun', fn (Builder $runs) => $runs->where('user_id', $user->id)));
        }
        $this->filterChannelSize($query, $user, $filters['channel_size'], 'channels.id');
        $this->filterResearchScore($query, $user, $filters, 'channel');
        $this->filterObservedDate($query, $user, $filters, 'channel', 'channels.id');
        $this->filterOrganization($query, $user, $filters['organization'], 'channels', 'channel');
    }

    /** @param Builder<NicheCandidate> $query
     * @param  array<string, mixed>  $filters
     */
    private function filterCandidateQuery(Builder $query, User $user, array $filters): void
    {
        if (! in_array($filters['source'], ['all', 'discovery', 'library'], true)) {
            $query->whereRaw('1 = 0');
        } elseif ($filters['source'] === 'library') {
            $this->whereFavorite($query, $user, 'niche_candidates', 'niche_candidate');
        }
        if ($filters['market']) {
            $query->whereHas('discoveryRun', fn (Builder $runs) => $runs->where('market_key', $filters['market']));
        }
        if ($filters['topic']) {
            $term = '%'.$this->escapeLike($filters['topic']).'%';
            $query->where(fn (Builder $q) => $q->where('phrase', 'like', $term)->orWhere('summary', 'like', $term)->orWhere('evidence', 'like', $term));
        }
        if ($filters['min_score'] !== null) {
            $query->where('overall_score', '>=', $filters['min_score']);
        }
        if ($filters['min_confidence'] !== null) {
            $query->where('confidence_score', '>=', $filters['min_confidence']);
        }
        if ($filters['observed_from']) {
            $query->where('niche_candidates.updated_at', '>=', Carbon::parse($filters['observed_from'], 'UTC')->startOfDay());
        }
        if ($filters['observed_to']) {
            $query->where('niche_candidates.updated_at', '<=', Carbon::parse($filters['observed_to'], 'UTC')->endOfDay());
        }
        $this->filterOrganization($query, $user, $filters['organization'], 'niche_candidates', 'niche_candidate', false);
    }

    /** @param Builder<Video> $query */
    private function selectVideoProjection(Builder $query, User $user): void
    {
        $query->addSelect([
            'observed_at' => DB::table('user_entity_observations')->select('last_seen_at')->whereColumn('subject_id', 'videos.id')->where('subject_type', 'video')->where('user_id', $user->id)->limit(1),
            'performance' => DB::table('video_analysis_metrics')->join('analyzer_runs', 'analyzer_runs.id', '=', 'video_analysis_metrics.analyzer_run_id')->select('lifetime_views_per_day')->whereColumn('video_analysis_metrics.video_id', 'videos.id')->where('analyzer_runs.user_id', $user->id)->orderByDesc('video_analysis_metrics.calculated_at')->limit(1),
            'breakout_class' => DB::table('video_analysis_metrics')->join('analyzer_runs', 'analyzer_runs.id', '=', 'video_analysis_metrics.analyzer_run_id')->select('breakout_class')->whereColumn('video_analysis_metrics.video_id', 'videos.id')->where('analyzer_runs.user_id', $user->id)->orderByDesc('video_analysis_metrics.calculated_at')->limit(1),
            'subscriber_count' => DB::table('user_entity_observations')->join('channel_snapshots', 'channel_snapshots.id', '=', 'user_entity_observations.latest_channel_snapshot_id')->select('subscriber_count')->whereColumn('user_entity_observations.subject_id', 'videos.channel_id')->where('subject_type', 'channel')->where('user_id', $user->id)->limit(1),
            'score' => DB::table('opportunity_scores')->join('research_runs', 'research_runs.id', '=', 'opportunity_scores.research_run_id')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->select('overall_score')->whereColumn('research_run_videos.video_id', 'videos.id')->where('research_runs.user_id', $user->id)->orderByDesc('opportunity_scores.calculated_at')->limit(1),
            'confidence' => DB::table('opportunity_scores')->join('research_runs', 'research_runs.id', '=', 'opportunity_scores.research_run_id')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->select('confidence_score')->whereColumn('research_run_videos.video_id', 'videos.id')->where('research_runs.user_id', $user->id)->orderByDesc('opportunity_scores.calculated_at')->limit(1),
            'market_key' => DB::table('research_runs')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->select('market_key')->whereColumn('research_run_videos.video_id', 'videos.id')->where('research_runs.user_id', $user->id)->orderByDesc('research_runs.completed_at')->limit(1),
            'category_name' => DB::table('video_categories')->select('name')->whereColumn('category_id', 'videos.category_id')->where('provider', 'youtube')->where('display_language', 'en')->orderBy('id')->limit(1),
            'analyzer_public_id' => DB::table('analyzer_runs')->select('public_id')->whereColumn('video_id', 'videos.id')->where('user_id', $user->id)->orderByDesc('created_at')->limit(1),
            'is_favorite' => DB::table('favorites')->selectRaw('1')->whereColumn('target_id', 'videos.id')->where('target_type', 'video')->where('user_id', $user->id)->limit(1),
            'research_status' => DB::table('analyzer_curations')->select('research_status')->whereColumn('subject_id', 'videos.id')->where('subject_type', 'video')->where('user_id', $user->id)->limit(1),
            'watchlist_public_id' => DB::table('watchlist_items')->select('public_id')->whereColumn('target_id', 'videos.id')->where('target_type', 'video')->where('user_id', $user->id)->limit(1),
            'has_research' => DB::table('research_run_videos')->join('research_runs', 'research_runs.id', '=', 'research_run_videos.research_run_id')->selectRaw('1')->whereColumn('research_run_videos.video_id', 'videos.id')->where('research_runs.user_id', $user->id)->limit(1),
            'has_analyzer' => DB::table('analyzer_runs')->selectRaw('1')->whereColumn('video_id', 'videos.id')->where('user_id', $user->id)->limit(1),
            'detected_niche' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('niche_label')->whereColumn('analyzer_runs.video_id', 'videos.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
            'semantic_language' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('language')->whereColumn('analyzer_runs.video_id', 'videos.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
            'semantic_version' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('algorithm_version')->whereColumn('analyzer_runs.video_id', 'videos.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
        ]);
    }

    /** @param Builder<Channel> $query */
    private function selectChannelProjection(Builder $query, User $user): void
    {
        $query->addSelect([
            'observed_at' => DB::table('user_entity_observations')->select('last_seen_at')->whereColumn('subject_id', 'channels.id')->where('subject_type', 'channel')->where('user_id', $user->id)->limit(1),
            'subscriber_count' => DB::table('user_entity_observations')->join('channel_snapshots', 'channel_snapshots.id', '=', 'user_entity_observations.latest_channel_snapshot_id')->select('subscriber_count')->whereColumn('user_entity_observations.subject_id', 'channels.id')->where('subject_type', 'channel')->where('user_id', $user->id)->limit(1),
            'performance' => DB::table('channel_analysis_metrics')->join('analyzer_runs', 'analyzer_runs.id', '=', 'channel_analysis_metrics.analyzer_run_id')->select('median_views')->whereColumn('channel_analysis_metrics.channel_id', 'channels.id')->where('analyzer_runs.user_id', $user->id)->orderByDesc('channel_analysis_metrics.calculated_at')->limit(1),
            'breakout_count' => DB::table('channel_analysis_metrics')->join('analyzer_runs', 'analyzer_runs.id', '=', 'channel_analysis_metrics.analyzer_run_id')->select('breakout_count')->whereColumn('channel_analysis_metrics.channel_id', 'channels.id')->where('analyzer_runs.user_id', $user->id)->orderByDesc('channel_analysis_metrics.calculated_at')->limit(1),
            'score' => DB::table('opportunity_scores')->join('research_runs', 'research_runs.id', '=', 'opportunity_scores.research_run_id')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->join('videos', 'videos.id', '=', 'research_run_videos.video_id')->select('overall_score')->whereColumn('videos.channel_id', 'channels.id')->where('research_runs.user_id', $user->id)->orderByDesc('opportunity_scores.calculated_at')->limit(1),
            'confidence' => DB::table('opportunity_scores')->join('research_runs', 'research_runs.id', '=', 'opportunity_scores.research_run_id')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->join('videos', 'videos.id', '=', 'research_run_videos.video_id')->select('confidence_score')->whereColumn('videos.channel_id', 'channels.id')->where('research_runs.user_id', $user->id)->orderByDesc('opportunity_scores.calculated_at')->limit(1),
            'market_key' => DB::table('research_runs')->join('research_run_videos', 'research_run_videos.research_run_id', '=', 'research_runs.id')->join('videos', 'videos.id', '=', 'research_run_videos.video_id')->select('market_key')->whereColumn('videos.channel_id', 'channels.id')->where('research_runs.user_id', $user->id)->orderByDesc('research_runs.completed_at')->limit(1),
            'analyzer_public_id' => DB::table('analyzer_runs')->select('public_id')->whereColumn('channel_id', 'channels.id')->where('user_id', $user->id)->orderByDesc('created_at')->limit(1),
            'is_favorite' => DB::table('favorites')->selectRaw('1')->whereColumn('target_id', 'channels.id')->where('target_type', 'channel')->where('user_id', $user->id)->limit(1),
            'research_status' => DB::table('analyzer_curations')->select('research_status')->whereColumn('subject_id', 'channels.id')->where('subject_type', 'channel')->where('user_id', $user->id)->limit(1),
            'watchlist_public_id' => DB::table('watchlist_items')->select('public_id')->whereColumn('target_id', 'channels.id')->where('target_type', 'channel')->where('user_id', $user->id)->limit(1),
            'has_research' => DB::table('research_run_videos')->join('research_runs', 'research_runs.id', '=', 'research_run_videos.research_run_id')->join('videos', 'videos.id', '=', 'research_run_videos.video_id')->selectRaw('1')->whereColumn('videos.channel_id', 'channels.id')->where('research_runs.user_id', $user->id)->limit(1),
            'has_analyzer' => DB::table('analyzer_runs')->selectRaw('1')->whereColumn('channel_id', 'channels.id')->where('user_id', $user->id)->limit(1),
            'detected_niche' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('niche_label')->whereColumn('analyzer_runs.channel_id', 'channels.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
            'semantic_language' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('language')->whereColumn('analyzer_runs.channel_id', 'channels.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
            'semantic_version' => DB::table('semantic_topic_profiles')->join('analyzer_runs', 'analyzer_runs.id', '=', 'semantic_topic_profiles.analyzer_run_id')->select('algorithm_version')->whereColumn('analyzer_runs.channel_id', 'channels.id')->where('semantic_topic_profiles.user_id', $user->id)->whereIn('semantic_topic_profiles.status', ['complete', 'partial'])->orderByDesc('semantic_topic_profiles.calculated_at')->limit(1),
        ]);
    }

    /** @param Builder<Video>|Builder<Channel> $query
     * @param  array<string, mixed>  $filters
     */
    private function filterResearchScore(Builder $query, User $user, array $filters, string $type): void
    {
        if ($filters['min_score'] === null && $filters['min_confidence'] === null) {
            return;
        }
        $relation = $type === 'video' ? 'researchRuns.opportunityScores' : 'videos.researchRuns.opportunityScores';
        $query->whereHas($relation, function (Builder $scores) use ($user, $filters): void {
            $scores->whereHas('researchRun', fn (Builder $runs) => $runs->where('user_id', $user->id));
            if ($filters['min_score'] !== null) {
                $scores->where('overall_score', '>=', $filters['min_score']);
            }
            if ($filters['min_confidence'] !== null) {
                $scores->where('confidence_score', '>=', $filters['min_confidence']);
            }
        });
    }

    /** @param Builder<Video>|Builder<Channel> $query */
    private function filterChannelSize(Builder $query, User $user, ?string $size, string $channelColumn): void
    {
        if ($size === null) {
            return;
        }
        $query->whereExists(function ($snapshots) use ($user, $size, $channelColumn): void {
            $snapshots->selectRaw('1')->from('user_entity_observations')
                ->leftJoin('channel_snapshots', 'channel_snapshots.id', '=', 'user_entity_observations.latest_channel_snapshot_id')
                ->whereColumn('user_entity_observations.subject_id', $channelColumn)
                ->where('user_entity_observations.subject_type', 'channel')->where('user_entity_observations.user_id', $user->id);
            match ($size) {
                'small' => $snapshots->where('channel_snapshots.subscriber_count', '<', 10000),
                'mid' => $snapshots->whereBetween('channel_snapshots.subscriber_count', [10000, 99999]),
                'large' => $snapshots->where('channel_snapshots.subscriber_count', '>=', 100000),
                default => $snapshots->whereNull('channel_snapshots.subscriber_count'),
            };
        });
    }

    /** @param Builder<Video>|Builder<Channel> $query
     * @param  array<string, mixed>  $filters
     */
    private function filterObservedDate(Builder $query, User $user, array $filters, string $type, string $column): void
    {
        if (! $filters['observed_from'] && ! $filters['observed_to']) {
            return;
        }
        $query->whereExists(function ($observations) use ($user, $filters, $type, $column): void {
            $observations->selectRaw('1')->from('user_entity_observations')->whereColumn('subject_id', $column)
                ->where('subject_type', $type)->where('user_id', $user->id);
            if ($filters['observed_from']) {
                $observations->where('last_seen_at', '>=', Carbon::parse($filters['observed_from'], 'UTC')->startOfDay());
            }
            if ($filters['observed_to']) {
                $observations->where('last_seen_at', '<=', Carbon::parse($filters['observed_to'], 'UTC')->endOfDay());
            }
        });
    }

    /** @param Builder<Video>|Builder<Channel>|Builder<NicheCandidate> $query */
    private function filterOrganization(Builder $query, User $user, string $organization, string $table, string $targetType, bool $curation = true): void
    {
        if ($organization === 'favorite') {
            $this->whereFavorite($query, $user, $table, $targetType);
        } elseif ($organization === 'not_favorite') {
            $query->whereNotExists(fn ($favorites) => $favorites->selectRaw('1')->from('favorites')->whereColumn('target_id', "{$table}.id")->where('target_type', $targetType)->where('user_id', $user->id));
        } elseif ($organization === 'curated' && $curation) {
            $query->whereExists(fn ($rows) => $rows->selectRaw('1')->from('analyzer_curations')->whereColumn('subject_id', "{$table}.id")->where('subject_type', $targetType)->where('user_id', $user->id)->where('research_status', '!=', 'unreviewed'));
        } elseif ($organization === 'unreviewed' && $curation) {
            $query->whereNotExists(fn ($rows) => $rows->selectRaw('1')->from('analyzer_curations')->whereColumn('subject_id', "{$table}.id")->where('subject_type', $targetType)->where('user_id', $user->id)->where('research_status', '!=', 'unreviewed'));
        } elseif (in_array($organization, ['curated', 'unreviewed'], true) && ! $curation) {
            $query->whereRaw('1 = 0');
        }
    }

    /** @param Builder<Video>|Builder<Channel>|Builder<NicheCandidate> $query */
    private function whereFavorite(Builder $query, User $user, string $table, string $targetType): void
    {
        $query->whereExists(fn ($favorites) => $favorites->selectRaw('1')->from('favorites')->whereColumn('target_id', "{$table}.id")->where('target_type', $targetType)->where('user_id', $user->id));
    }

    /** @param Builder<Video>|Builder<Channel>|Builder<NicheCandidate> $query
     * @param  array<string, mixed>  $filters
     */
    private function sort(Builder $query, array $filters, string $titleColumn): void
    {
        match ($filters['sort']) {
            'title' => $query->orderBy($titleColumn),
            'score_desc' => $query->orderByDesc('score'),
            'performance_desc' => $query->orderByDesc('performance'),
            default => $query->orderByDesc($query->getModel()->qualifyColumn('updated_at')),
        };
        $query->orderByDesc($query->getModel()->qualifyColumn('id'));
    }

    /** @return list<string> */
    private function sources(Video|Channel $entity): array
    {
        return array_values(array_filter([
            $entity->getAttribute('has_research') ? 'research' : null,
            $entity->getAttribute('has_analyzer') ? 'analyzer' : null,
            $entity->getAttribute('is_favorite') ? 'library' : null,
        ]));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  array<int, array<string, mixed>>  $items
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function paginationPayload(LengthAwarePaginator $page, array $items): array
    {
        return [
            'data' => array_values($items),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array{niche: string, language: string|null, version: string|null}|null */
    private function semanticSummary(Video|Channel $entity): ?array
    {
        $niche = $entity->getAttribute('detected_niche');
        if (! is_string($niche) || $niche === '') {
            return null;
        }

        $language = $entity->getAttribute('semantic_language');
        $version = $entity->getAttribute('semantic_version');

        return [
            'niche' => $niche,
            'language' => is_string($language) ? $language : null,
            'version' => is_string($version) ? $version : null,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function analyzerUrl(string $kind, string $providerId, mixed $analyzerPublicId, array $filters, int $page): string
    {
        $returnUrl = '/explore?'.http_build_query(array_filter([...$filters, 'page' => $page], fn (mixed $value): bool => is_scalar($value) && $value !== ''), '', '&', PHP_QUERY_RFC3986);
        $base = is_string($analyzerPublicId) && $analyzerPublicId !== ''
            ? '/analyzer/runs/'.$analyzerPublicId
            : '/analyzer?'.$kind.'='.rawurlencode($providerId).'&origin=explore';

        return $base.(str_contains($base, '?') ? '&' : '?').'return_to='.rawurlencode($returnUrl);
    }
}
