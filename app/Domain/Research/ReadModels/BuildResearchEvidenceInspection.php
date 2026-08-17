<?php

namespace App\Domain\Research\ReadModels;

use App\Domain\Scoring\Services\ResearchEvidenceV1;
use App\Models\ResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class BuildResearchEvidenceInspection
{
    private const PAGE_SIZE = 10;

    private const ENGAGEMENT_SQL = '(CASE WHEN video_snapshot.view_count > 0 AND video_snapshot.like_count IS NOT NULL AND video_snapshot.comment_count IS NOT NULL THEN ((video_snapshot.like_count + video_snapshot.comment_count) * 100.0) / video_snapshot.view_count ELSE NULL END)';

    /**
     * @param  array{sort: string, direction: string, filter: string, page: int}  $input
     * @return array<string, mixed>
     */
    public function handle(ResearchRun $run, array $input): array
    {
        $profile = $run->evidenceProfiles()->where('evidence_version', ResearchEvidenceV1::VERSION)->first();
        $breakoutClass = $this->breakoutClassSubquery($run);
        $query = DB::table('research_run_videos as membership')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->join('channels as channel', 'channel.id', '=', 'video.channel_id')
            ->leftJoin('video_snapshots as video_snapshot', 'video_snapshot.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_snapshot', 'channel_snapshot.id', '=', 'membership.channel_snapshot_id')
            ->leftJoin('research_result_evidence as result_evidence', function ($join) use ($profile): void {
                $join->on('result_evidence.research_run_id', '=', 'membership.research_run_id')
                    ->on('result_evidence.video_id', '=', 'membership.video_id');
                if ($profile !== null) {
                    $join->where('result_evidence.research_evidence_profile_id', '=', $profile->id);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->where('membership.research_run_id', $run->id);

        $this->applyFilter($query, $input['filter'], $run, $profile !== null);

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($input['page'], $lastPage);
        $direction = $this->direction($input['direction']);
        $this->applySort($query, $input['sort'], $direction);

        /** @var list<stdClass> $records */
        $records = $query
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->select([
                'video.provider_video_id',
                'video.title',
                'video.thumbnail_url',
                'video.published_at',
                'video.duration_seconds',
                'video.is_short',
                'channel.provider_channel_id',
                'channel.title as channel_title',
                'membership.result_rank',
                'video_snapshot.view_count',
                'video_snapshot.like_count',
                'video_snapshot.comment_count',
                'video_snapshot.views_per_day',
                'video_snapshot.views_to_subscribers_ratio',
                'video_snapshot.collected_at',
                'channel_snapshot.subscriber_count',
                'channel_snapshot.subscriber_count_hidden',
                'result_evidence.relevance_class',
                'result_evidence.relevance_score',
                'result_evidence.signals as relevance_signals',
            ])
            ->selectRaw(self::ENGAGEMENT_SQL.' as engagement_rate')
            ->selectSub($breakoutClass, 'breakout_class')
            ->get()
            ->all();

        return [
            'items' => array_map(fn (stdClass $record): array => $this->row($record), $records),
            'pagination' => [
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? 0 : (($page - 1) * self::PAGE_SIZE) + 1,
                'to' => min($page * self::PAGE_SIZE, $total),
            ],
            'query' => [
                'sort' => $input['sort'],
                'direction' => $direction,
                'filter' => $input['filter'],
            ],
            'limits' => [
                'page_size' => self::PAGE_SIZE,
                'channel_size_bands' => [
                    'small' => 'Under 100K subscribers',
                    'mid_size' => '100K–999,999 subscribers',
                    'large' => '1M+ subscribers',
                ],
            ],
            'relevance' => [
                'state' => $profile === null ? 'provider_order_only' : 'versioned',
                'label' => $profile === null ? 'Provider relevance order' : 'Versioned relevance evidence',
                'version' => $profile?->evidence_version,
                'description' => $profile === null
                    ? 'This historical run has no versioned relevance profile. The relevance sort preserves its captured provider order.'
                    : 'Relevance combines frozen title, semantic, category, topic, negative-term, language, and format signals. Expand a row for exact evidence.',
            ],
            'filters' => [
                ['key' => 'all', 'label' => 'All evidence', 'enabled' => true, 'reason' => null],
                ['key' => 'strictly_relevant', 'label' => 'Strictly relevant', 'enabled' => $profile !== null, 'reason' => $profile === null ? 'No versioned relevance profile is stored for this historical run.' : null],
                ['key' => 'small_channels', 'label' => 'Small channels', 'enabled' => true, 'reason' => null],
                ['key' => 'mid_size_channels', 'label' => 'Mid-size channels', 'enabled' => true, 'reason' => null],
                ['key' => 'large_channels', 'label' => 'Large channels', 'enabled' => true, 'reason' => null],
                ['key' => 'breakouts', 'label' => 'Breakouts', 'enabled' => true, 'reason' => 'Uses an existing owner-scoped Analyzer Breakout classification when available.'],
                ['key' => 'long_form', 'label' => 'Long-form', 'enabled' => true, 'reason' => null],
                ['key' => 'shorts', 'label' => 'Shorts', 'enabled' => true, 'reason' => null],
                ['key' => 'complete_metrics', 'label' => 'Complete metrics', 'enabled' => true, 'reason' => null],
            ],
        ];
    }

    private function applyFilter(Builder $query, string $filter, ResearchRun $run, bool $hasProfile): void
    {
        match ($filter) {
            'strictly_relevant' => $hasProfile ? $query->where('result_evidence.relevance_class', 'strictly_relevant') : $query->whereRaw('1 = 0'),
            'small_channels' => $query->whereNotNull('channel_snapshot.subscriber_count')->where('channel_snapshot.subscriber_count', '<', 100000),
            'mid_size_channels' => $query->whereBetween('channel_snapshot.subscriber_count', [100000, 999999]),
            'large_channels' => $query->where('channel_snapshot.subscriber_count', '>=', 1000000),
            'breakouts' => $query->whereExists(fn (Builder $exists): Builder => $this->breakoutExists($exists, $run)),
            'long_form' => $query->where('video.is_short', false),
            'shorts' => $query->where('video.is_short', true),
            'complete_metrics' => $query
                ->whereNotNull('video_snapshot.view_count')
                ->whereNotNull('video_snapshot.like_count')
                ->whereNotNull('video_snapshot.comment_count')
                ->whereNotNull('video_snapshot.views_per_day')
                ->whereNotNull('video_snapshot.views_to_subscribers_ratio')
                ->whereNotNull('channel_snapshot.subscriber_count'),
            default => null,
        };
    }

    /** @param 'asc'|'desc' $direction */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'views_per_day' => $query->orderByRaw('video_snapshot.views_per_day IS NULL')->orderBy('video_snapshot.views_per_day', $direction),
            'engagement' => $query
                ->orderByRaw(self::ENGAGEMENT_SQL.' IS NULL')
                ->orderByRaw($direction === 'asc' ? self::ENGAGEMENT_SQL.' asc' : self::ENGAGEMENT_SQL.' desc'),
            'channel_size' => $query->orderByRaw('channel_snapshot.subscriber_count IS NULL')->orderBy('channel_snapshot.subscriber_count', $direction),
            'reach_ratio' => $query->orderByRaw('video_snapshot.views_to_subscribers_ratio IS NULL')->orderBy('video_snapshot.views_to_subscribers_ratio', $direction),
            'published_at' => $query->orderBy('video.published_at', $direction),
            'breakout_class' => $query->orderByRaw('breakout_class IS NULL')->orderBy('breakout_class', $direction),
            'relevance' => $query->orderByRaw('result_evidence.relevance_score IS NULL')->orderBy('result_evidence.relevance_score', $direction),
            default => $query->orderBy('membership.result_rank', $direction),
        };

        $query->orderBy('membership.result_rank')->orderBy('video.id');
    }

    /** @return 'asc'|'desc' */
    private function direction(string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    private function breakoutClassSubquery(ResearchRun $run): Builder
    {
        return DB::table('video_analysis_metrics as metric')
            ->join('analyzer_runs as analyzer', 'analyzer.id', '=', 'metric.analyzer_run_id')
            ->select('metric.breakout_class')
            ->whereColumn('metric.video_id', 'video.id')
            ->where('analyzer.user_id', $run->user_id)
            ->whereNotNull('metric.breakout_class')
            ->orderByDesc('metric.calculated_at')
            ->orderByDesc('metric.id')
            ->limit(1);
    }

    private function breakoutExists(Builder $query, ResearchRun $run): Builder
    {
        return $query
            ->selectRaw('1')
            ->from('video_analysis_metrics as metric_filter')
            ->join('analyzer_runs as analyzer_filter', 'analyzer_filter.id', '=', 'metric_filter.analyzer_run_id')
            ->whereColumn('metric_filter.video_id', 'video.id')
            ->where('analyzer_filter.user_id', $run->user_id)
            ->where('metric_filter.breakout_class', 'breakout');
    }

    /** @return array<string, mixed> */
    private function row(stdClass $record): array
    {
        return [
            'provider_video_id' => (string) $record->provider_video_id,
            'title' => (string) $record->title,
            'thumbnail_url' => $record->thumbnail_url !== null ? (string) $record->thumbnail_url : null,
            'channel_id' => (string) $record->provider_channel_id,
            'channel_title' => (string) $record->channel_title,
            'result_rank' => (int) $record->result_rank,
            'relevance' => $record->relevance_class === null ? null : [
                'class' => (string) $record->relevance_class,
                'score' => (float) $record->relevance_score,
                'signals' => $this->metadata($record->relevance_signals),
            ],
            'view_count' => $record->view_count !== null ? (int) $record->view_count : null,
            'views_per_day' => $record->views_per_day !== null ? (float) $record->views_per_day : null,
            'engagement_rate' => $record->engagement_rate !== null ? round((float) $record->engagement_rate, 4) : null,
            'subscriber_count' => $record->subscriber_count !== null ? (int) $record->subscriber_count : null,
            'subscriber_count_hidden' => (bool) ($record->subscriber_count_hidden ?? false),
            'reach_ratio' => $record->views_to_subscribers_ratio !== null ? (float) $record->views_to_subscribers_ratio : null,
            'published_at' => CarbonImmutable::parse((string) $record->published_at)->toIso8601String(),
            'collected_at' => $record->collected_at !== null ? CarbonImmutable::parse((string) $record->collected_at)->toIso8601String() : null,
            'duration_seconds' => $record->duration_seconds !== null ? (int) $record->duration_seconds : null,
            'format' => $record->is_short === null ? 'unknown' : ((bool) $record->is_short ? 'shorts' : 'long_form'),
            'breakout_class' => $record->breakout_class !== null ? (string) $record->breakout_class : null,
            'metrics_complete' => ! in_array(null, [
                $record->view_count,
                $record->like_count,
                $record->comment_count,
                $record->views_per_day,
                $record->views_to_subscribers_ratio,
                $record->subscriber_count,
            ], true),
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
