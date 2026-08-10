<?php

namespace App\Domain\Analyzer\ReadModels;

use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\ChannelAnalysisMetric;
use App\Models\ChannelSnapshot;
use App\Models\ResearchRun;
use App\Models\SemanticPerformanceAggregate;
use App\Models\SemanticPerformanceProfile;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\ThumbnailPerformanceAggregate;
use App\Models\TopicWorkspace;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

final class BuildCrossChannelComparison
{
    private const AGGREGATE_LIMIT = 100;

    /** @return array<string, mixed> */
    public function handle(User $user, AnalyzerRun ...$selectedRuns): array
    {
        if (count($selectedRuns) < 2 || count($selectedRuns) > 3) {
            throw new DomainException('Choose two or three channel analyses.');
        }

        $runs = collect(array_values($selectedRuns));
        $runs->each(fn (AnalyzerRun $run) => $this->assertOwnedCompletedChannelRun($user, $run));

        if ($runs->pluck('channel_id')->unique()->count() !== $runs->count()) {
            throw new DomainException('Choose one analysis attempt per channel.');
        }

        $runs->each(fn (AnalyzerRun $run) => $run->loadMissing([
            'channel', 'channelSnapshot', 'channelMetrics',
            'semanticPerformanceProfile.aggregates',
        ]));

        $thumbnails = ThumbnailAnalysisProfile::query()
            ->where('user_id', $user->id)
            ->whereIn('analyzer_run_id', $runs->pluck('id'))
            ->whereIn('status', ['complete', 'partial', 'insufficient'])
            ->with(['aggregates' => fn ($query) => $query->limit(self::AGGREGATE_LIMIT)])
            ->latest('attempt_number')
            ->get()
            ->unique('analyzer_run_id')
            ->keyBy('analyzer_run_id');

        $markets = $runs->mapWithKeys(fn (AnalyzerRun $run): array => [
            $run->id => $this->marketContext($user, $run),
        ])->all();
        $compatibility = $this->compatibility($runs, $thumbnails, $markets);

        return [
            'runs' => $runs->map(fn (AnalyzerRun $run): array => $this->run($run, $markets[$run->id], $thumbnails->get($run->id)))->values()->all(),
            'compatibility' => $compatibility,
            'channel_metrics' => $this->channelMetricRows($runs),
            'topic_rows' => $this->semanticRows($runs, 'topic'),
            'title_pattern_rows' => $this->semanticRows($runs, 'title_pattern'),
            'thumbnail_rows' => $this->thumbnailRows($runs, $thumbnails),
            'disclaimer' => 'Side-by-side values describe separate stored cohorts. They are not an opportunity score, causal finding, or channel recommendation.',
        ];
    }

    private function assertOwnedCompletedChannelRun(User $user, AnalyzerRun $run): void
    {
        if ($run->user_id !== $user->id || $run->status->value !== 'completed' || $run->channel_id === null || $run->channel_snapshot_id === null) {
            throw new DomainException('Only your completed channel analyses with pinned observations can be compared.');
        }

        $this->metric($run);
    }

    /** @param Collection<int, AnalyzerRun> $runs
     * @param  Collection<int, ThumbnailAnalysisProfile>  $thumbnails
     * @param  array<int, array{key: string|null, source: string}>  $markets
     * @return array<string, mixed>
     */
    private function compatibility(Collection $runs, Collection $thumbnails, array $markets): array
    {
        $warnings = [];
        $metrics = $runs->map(fn (AnalyzerRun $run): ChannelAnalysisMetric => $this->metric($run));
        $channelCompatible = $metrics->map(fn (ChannelAnalysisMetric $metric): string => implode('|', [
            $metric->calculation_version ?? '',
            $metric->behavior_version ?? '',
            $metric->threshold_version ?? '',
        ]))->unique()->count() === 1;
        if (! $channelCompatible) {
            $warnings[] = $this->warning('channel_model_mismatch', 'Channel metric versions are missing or differ; these values are not like-for-like.');
        }

        $semantics = $runs->map(fn (AnalyzerRun $run): ?SemanticPerformanceProfile => $this->semantic($run))
            ->filter(fn (?SemanticPerformanceProfile $profile): bool => $profile !== null);
        $topicCompatible = $semantics->count() === $runs->count()
            && $semantics->map(fn (SemanticPerformanceProfile $profile): string => implode('|', [
                $profile->calculation_version,
                $profile->topic_version ?? '',
            ]))->unique()->count() === 1;
        $titleCompatible = $semantics->count() === $runs->count()
            && $semantics->map(fn (SemanticPerformanceProfile $profile): string => implode('|', [
                $profile->calculation_version,
                $profile->title_pattern_version,
            ]))->unique()->count() === 1;
        if (! $topicCompatible) {
            $warnings[] = $this->warning('topic_model_mismatch', 'Topic performance is missing or uses different model versions.');
        }
        if (! $titleCompatible) {
            $warnings[] = $this->warning('title_model_mismatch', 'Title-pattern performance is missing or uses different model versions.');
        }

        $selectedThumbnails = $runs->map(fn (AnalyzerRun $run) => $thumbnails->get($run->id))
            ->filter(fn (?ThumbnailAnalysisProfile $profile): bool => $profile !== null);
        $thumbnailCompatible = $selectedThumbnails->count() === $runs->count()
            && $selectedThumbnails->map(fn (ThumbnailAnalysisProfile $profile): string => implode('|', [
                $profile->provider,
                $profile->algorithm_version,
                $profile->calculation_version,
            ]))->unique()->count() === 1;
        if (! $thumbnailCompatible) {
            $warnings[] = $this->warning('thumbnail_model_mismatch', 'Thumbnail performance is missing or uses different provider/model versions.');
        }

        $marketKeys = $runs->map(fn (AnalyzerRun $run) => $markets[$run->id]['key']);
        if ($marketKeys->contains(null)) {
            $warnings[] = $this->warning('market_unknown', 'At least one Analyzer attempt has no frozen Search or Topic Workspace market context.');
        } elseif ($marketKeys->unique()->count() > 1) {
            $warnings[] = $this->warning('market_mismatch', 'Market contexts differ across the selected channel analyses.');
        }

        $observedAt = $runs->map(fn (AnalyzerRun $run) => $this->snapshot($run)->collected_at)->sort()->values();
        if ($observedAt->first()->diffInDays($observedAt->last()) > 30) {
            $warnings[] = $this->warning('time_window_mismatch', 'Observation times are more than 30 days apart; platform conditions may not be comparable.');
        }
        if ($metrics->pluck('recent_valid_count')->unique()->count() > 1) {
            $warnings[] = $this->warning('sample_size_mismatch', 'Valid cohort sample sizes differ; use each row\'s exact sample counts.');
        }
        if ($runs->map(fn (AnalyzerRun $run): string => $run->cache_policy->value)->unique()->count() > 1) {
            $warnings[] = $this->warning('source_policy_mismatch', 'The attempts used different cache/source policies; original observation times are shown.');
        }

        return [
            'channel_metrics' => $channelCompatible,
            'topics' => $topicCompatible,
            'title_patterns' => $titleCompatible,
            'thumbnails' => $thumbnailCompatible,
            'warnings' => $warnings,
        ];
    }

    /** @return array{code: string, message: string} */
    private function warning(string $code, string $message): array
    {
        return compact('code', 'message');
    }

    /** @param array{key: string|null, source: string} $market
     * @return array<string, mixed>
     */
    private function run(AnalyzerRun $run, array $market, ?ThumbnailAnalysisProfile $thumbnail): array
    {
        $metric = $this->metric($run);
        $semantic = $this->semantic($run);
        $channel = $this->channel($run);
        $snapshot = $this->snapshot($run);

        return [
            'public_id' => $run->public_id,
            'channel_title' => $channel->title,
            'provider_channel_id' => $channel->provider_channel_id,
            'observed_at' => $snapshot->collected_at->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'cache_policy' => $run->cache_policy->value,
            'market' => $market,
            'cohort_video_count' => $metric->recent_valid_count,
            'requested_video_count' => $metric->recent_requested_count,
            'channel_model' => [
                'calculation_version' => $metric->calculation_version,
                'behavior_version' => $metric->behavior_version,
                'threshold_version' => $metric->threshold_version,
            ],
            'semantic_model' => $semantic === null ? null : [
                'status' => $semantic->status,
                'calculation_version' => $semantic->calculation_version,
                'topic_version' => $semantic->topic_version,
                'title_pattern_version' => $semantic->title_pattern_version,
                'minimum_sample_size' => $semantic->minimum_sample_size,
                'cohort_video_count' => $semantic->cohort_video_count,
                'calculated_at' => $semantic->calculated_at->toIso8601String(),
            ],
            'thumbnail_model' => $thumbnail === null ? null : [
                'status' => $thumbnail->status->value,
                'provider' => $thumbnail->provider,
                'algorithm_version' => $thumbnail->algorithm_version,
                'calculation_version' => $thumbnail->calculation_version,
                'minimum_sample_size' => $thumbnail->minimum_sample_size,
                'cohort_video_count' => $thumbnail->cohort_video_count,
                'calculated_at' => $thumbnail->calculated_at?->toIso8601String(),
            ],
        ];
    }

    /** @param Collection<int, AnalyzerRun> $runs
     * @return list<array<string, mixed>>
     */
    private function channelMetricRows(Collection $runs): array
    {
        $fields = [
            'median_views' => ['Median views', 'count'],
            'median_age_days' => ['Median video age', 'days'],
            'videos_per_month' => ['Uploads per month', 'rate'],
            'strong_share_percent' => ['Strong share', 'percent'],
            'breakout_share_percent' => ['Breakout share', 'percent'],
            'momentum_ratio' => ['Momentum ratio', 'ratio'],
            'consistency_score' => ['Consistency score', 'score'],
            'duration_performance_correlation' => ['Duration/performance correlation', 'coefficient'],
        ];

        $rows = collect($fields)->map(function (array $meta, string $field) use ($runs): array {
            return [
                'key' => $field,
                'label' => $meta[0],
                'unit' => $meta[1],
                'values' => $runs->map(function (AnalyzerRun $run) use ($field): array {
                    $metric = $this->metric($run);
                    $metricValue = $metric->{$field};

                    return [
                        'value' => $metricValue === null ? null : (float) $metricValue,
                        'sample_count' => $this->channelSampleCount($run, $field),
                    ];
                })->all(),
            ];
        })->values()->all();

        return array_values($rows);
    }

    private function channelSampleCount(AnalyzerRun $run, string $field): int
    {
        $metric = $this->metric($run);

        return match ($field) {
            'momentum_ratio' => min($metric->momentum_recent_count, $metric->momentum_previous_count),
            'consistency_score' => $metric->consistency_sample_count,
            'duration_performance_correlation' => $metric->duration_performance_sample_count,
            default => $metric->recent_valid_count,
        };
    }

    /** @param Collection<int, AnalyzerRun> $runs
     * @return list<array<string, mixed>>
     */
    private function semanticRows(Collection $runs, string $type): array
    {
        $byRun = $runs->mapWithKeys(fn (AnalyzerRun $run): array => [
            $run->id => $this->semantic($run)?->aggregates
                ->where('group_type', $type)->take(self::AGGREGATE_LIMIT)->keyBy('label_key') ?? collect(),
        ]);
        $keys = $byRun->flatMap(fn (Collection $rows) => $rows->keys())->unique()->take(self::AGGREGATE_LIMIT);

        $result = $keys->map(function (string $key) use ($runs, $byRun): array {
            $first = $byRun->first(fn (Collection $rows) => $rows->has($key))?->get($key);

            return [
                'key' => $key,
                'label' => $first instanceof SemanticPerformanceAggregate ? $first->label : $key,
                'values' => $runs->map(function (AnalyzerRun $run) use ($byRun, $key): ?array {
                    $row = $byRun->get($run->id)->get($key);

                    return $row === null ? null : [
                        'sample_count' => $row->sample_count,
                        'view_sample_count' => $row->view_sample_count,
                        'median_views' => $this->float($row->median_views),
                        'views_per_day_sample_count' => $row->views_per_day_sample_count,
                        'median_views_per_day' => $this->float($row->median_views_per_day),
                        'breakout_count' => $row->breakout_count,
                        'breakout_sample_count' => $row->breakout_sample_count,
                        'breakout_rate_percent' => $this->float($row->breakout_rate_percent),
                    ];
                })->all(),
            ];
        })->values()->all();

        return array_values($result);
    }

    /** @param Collection<int, AnalyzerRun> $runs
     * @param  Collection<int, ThumbnailAnalysisProfile>  $thumbnails
     * @return list<array<string, mixed>>
     */
    private function thumbnailRows(Collection $runs, Collection $thumbnails): array
    {
        $byRun = $runs->mapWithKeys(fn (AnalyzerRun $run): array => [
            $run->id => $thumbnails->get($run->id) === null
                ? collect()
                : $thumbnails->get($run->id)->aggregates->keyBy('cluster_key'),
        ]);
        $keys = $byRun->flatMap(fn (Collection $rows) => $rows->keys())->unique()->take(self::AGGREGATE_LIMIT);

        $result = $keys->map(function (string $key) use ($runs, $byRun): array {
            $first = $byRun->first(fn (Collection $rows) => $rows->has($key))?->get($key);

            return [
                'key' => $key,
                'label' => $first instanceof ThumbnailPerformanceAggregate ? $first->label : $key,
                'values' => $runs->map(function (AnalyzerRun $run) use ($byRun, $key): ?array {
                    $row = $byRun->get($run->id)->get($key);

                    return $row === null ? null : [
                        'sample_count' => $row->sample_count,
                        'view_sample_count' => $row->view_sample_count,
                        'median_views' => $this->float($row->median_views),
                        'views_per_day_sample_count' => $row->views_per_day_sample_count,
                        'median_views_per_day' => $this->float($row->median_views_per_day),
                        'breakout_count' => $row->breakout_count,
                        'breakout_sample_count' => $row->breakout_sample_count,
                        'breakout_rate_percent' => $this->float($row->breakout_rate_percent),
                    ];
                })->all(),
            ];
        })->values()->all();

        return array_values($result);
    }

    /** @return array{key: string|null, source: string} */
    private function marketContext(User $user, AnalyzerRun $run, int $depth = 0): array
    {
        if ($depth < 4 && $run->origin_kind === 'refresh' && $run->origin_reference !== null) {
            $source = AnalyzerRun::query()->where('user_id', $user->id)->where('public_id', $run->origin_reference)->first();
            if ($source !== null) {
                return $this->marketContext($user, $source, $depth + 1);
            }
        }
        if ($run->origin_kind === 'search' && $run->origin_reference !== null) {
            $market = ResearchRun::query()->where('user_id', $user->id)->where('public_id', $run->origin_reference)->value('market_key');

            return ['key' => $market === null ? null : (string) $market, 'source' => 'Search'];
        }
        if ($run->origin_kind === 'topic_workspace' && $run->origin_reference !== null) {
            $market = TopicWorkspace::query()->where('user_id', $user->id)->where('public_id', $run->origin_reference)->value('market_key');

            return ['key' => $market === null ? null : (string) $market, 'source' => 'Topic Workspace'];
        }

        return ['key' => null, 'source' => 'Not frozen'];
    }

    private function float(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function metric(AnalyzerRun $run): ChannelAnalysisMetric
    {
        $metric = $run->getRelationValue('channelMetrics');

        if (! $metric instanceof ChannelAnalysisMetric) {
            throw new DomainException('A selected channel analysis has no stored channel metrics.');
        }

        return $metric;
    }

    private function semantic(AnalyzerRun $run): ?SemanticPerformanceProfile
    {
        $profile = $run->getRelationValue('semanticPerformanceProfile');

        return $profile instanceof SemanticPerformanceProfile ? $profile : null;
    }

    private function channel(AnalyzerRun $run): Channel
    {
        $channel = $run->getRelationValue('channel');

        if (! $channel instanceof Channel) {
            throw new DomainException('A selected channel analysis has no stored channel identity.');
        }

        return $channel;
    }

    private function snapshot(AnalyzerRun $run): ChannelSnapshot
    {
        $snapshot = $run->getRelationValue('channelSnapshot');

        if (! $snapshot instanceof ChannelSnapshot) {
            throw new DomainException('A selected channel analysis has no pinned channel observation.');
        }

        return $snapshot;
    }
}
