<?php

namespace App\Domain\History\ReadModels;

use App\Domain\History\Services\ResearchRunCompatibility;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class BuildResearchRunComparison
{
    private const COMPONENTS = [
        'demand_momentum' => 'demand_momentum_score',
        'competition_opportunity' => 'competition_opportunity_score',
        'audience_reachability' => 'audience_reachability_score',
        'content_freshness_gap' => 'content_freshness_gap_score',
        'creator_viability' => 'creator_viability_score',
    ];

    public function __construct(private readonly ResearchRunCompatibility $compatibility) {}

    /** @return array<string, mixed> */
    public function handle(User $user, ResearchRun $before, ResearchRun $after): array
    {
        $this->compatibility->authorize($user, $before, $after);
        $this->compatibility->assertComparable($before, $after);

        $runIds = [$before->id, $after->id];
        $scores = $this->scores($runIds);
        $beforeScore = $scores->get($before->id);
        $afterScore = $scores->get($after->id);
        $videoRows = $this->videoRows($runIds);
        $channelRows = $this->channelRows($runIds);
        $beforeVideos = $this->keyedRows($videoRows->get($before->id, collect()), 'video_id');
        $afterVideos = $this->keyedRows($videoRows->get($after->id, collect()), 'video_id');
        $beforeChannels = $this->keyedRows($channelRows->get($before->id, collect()), 'channel_id');
        $afterChannels = $this->keyedRows($channelRows->get($after->id, collect()), 'channel_id');
        $scoresComparable = $this->compatibility->scoresComparable($beforeScore, $afterScore);

        return [
            'before' => $this->runSummary($before, $beforeScore),
            'after' => $this->runSummary($after, $afterScore),
            'compatibility' => [
                'comparable' => true,
                'score_comparable' => $scoresComparable,
                'parameter_changes' => $this->compatibility->parameterChanges($before, $after),
                'warnings' => $this->compatibility->warnings($before, $after, $beforeScore, $afterScore),
            ],
            'score_deltas' => $this->scoreDeltas($beforeScore, $afterScore, $scoresComparable),
            'metric_deltas' => $this->metricDeltas(
                $this->metrics($beforeVideos, $beforeChannels),
                $this->metrics($afterVideos, $afterChannels),
            ),
            'videos' => $this->entityChanges($beforeVideos, $afterVideos, true),
            'channels' => $this->entityChanges($beforeChannels, $afterChannels, false),
            'sample_overlap' => $this->sampleOverlap($beforeVideos, $afterVideos),
            'stability' => $this->rankStability($beforeVideos, $afterVideos),
            'new_breakout_channels' => [
                'value' => null,
                'reason' => 'No snapshot-specific breakout-channel classification was stored for this pair.',
            ],
        ];
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<int, OpportunityScore>
     */
    private function scores(array $runIds): Collection
    {
        return OpportunityScore::query()
            ->whereIn('research_run_id', $runIds)
            ->latest('calculated_at')
            ->latest('id')
            ->get()
            ->groupBy('research_run_id')
            ->map(fn (Collection $scores): OpportunityScore => $scores->first());
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function videoRows(array $runIds): Collection
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('research_run_videos as membership')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->leftJoin('video_snapshots as snapshot', 'snapshot.id', '=', 'membership.video_snapshot_id')
            ->whereIn('membership.research_run_id', $runIds)
            ->select([
                'membership.research_run_id',
                'video.id as video_id',
                'video.provider_video_id',
                'video.title',
                'video.provider',
                'membership.result_rank',
                'snapshot.view_count',
                'snapshot.like_count',
                'snapshot.comment_count',
                'snapshot.views_per_day',
                'snapshot.views_to_subscribers_ratio',
            ])
            ->orderBy('membership.result_rank')
            ->get();

        return $rows->map(fn (stdClass $row): array => $this->videoRow($row))->groupBy('research_run_id');
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<int|string, Collection<int, array<string, mixed>>>
     */
    private function channelRows(array $runIds): Collection
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('research_run_videos as membership')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->join('channels as channel', 'channel.id', '=', 'video.channel_id')
            ->leftJoin('channel_snapshots as snapshot', 'snapshot.id', '=', 'membership.channel_snapshot_id')
            ->whereIn('membership.research_run_id', $runIds)
            ->select([
                'membership.research_run_id',
                'channel.id as channel_id',
                'channel.provider_channel_id',
                'channel.title',
                'channel.provider',
                'snapshot.subscriber_count',
                'snapshot.view_count',
                'snapshot.video_count',
                'snapshot.subscriber_count_hidden',
            ])
            ->distinct()
            ->get();

        return $rows->map(fn (stdClass $row): array => $this->channelRow($row))->groupBy('research_run_id');
    }

    /** @return array<string, mixed> */
    private function videoRow(stdClass $row): array
    {
        $views = $this->nullableInt($row->view_count);
        $likes = $this->nullableInt($row->like_count);
        $comments = $this->nullableInt($row->comment_count);

        return [
            'research_run_id' => (int) $row->research_run_id,
            'video_id' => (int) $row->video_id,
            'provider' => (string) $row->provider,
            'provider_video_id' => (string) $row->provider_video_id,
            'title' => (string) $row->title,
            'result_rank' => (int) $row->result_rank,
            'view_count' => $views,
            'views_per_day' => $this->nullableFloat($row->views_per_day),
            'reach_ratio' => $this->nullableFloat($row->views_to_subscribers_ratio),
            'engagement_rate' => $views !== null && $views > 0 && $likes !== null && $comments !== null
                ? (($likes + $comments) / $views) * 100
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function channelRow(stdClass $row): array
    {
        return [
            'research_run_id' => (int) $row->research_run_id,
            'channel_id' => (int) $row->channel_id,
            'provider' => (string) $row->provider,
            'provider_channel_id' => (string) $row->provider_channel_id,
            'title' => (string) $row->title,
            'subscriber_count' => $this->nullableInt($row->subscriber_count),
            'view_count' => $this->nullableInt($row->view_count),
            'video_count' => $this->nullableInt($row->video_count),
            'subscriber_count_hidden' => (bool) ($row->subscriber_count_hidden ?? false),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function keyedRows(Collection $rows, string $key): Collection
    {
        return $rows->keyBy(fn (array $row): int => (int) $row[$key]);
    }

    /** @return array<string, mixed> */
    private function runSummary(ResearchRun $run, ?OpportunityScore $score): array
    {
        return [
            'public_id' => $run->public_id,
            'query_text' => $run->query_text,
            'market_key' => $run->market_key,
            'kind' => $run->kind->value,
            'requested_result_count' => $run->requested_result_count,
            'collected_result_count' => $run->collected_result_count,
            'completed_at' => $run->completed_at?->toIso8601String(),
            'formula_version' => $score?->formula_version,
        ];
    }

    /** @return array<string, mixed> */
    private function scoreDeltas(
        ?OpportunityScore $before,
        ?OpportunityScore $after,
        bool $comparable,
    ): array {
        $components = [];

        foreach (self::COMPONENTS as $key => $attribute) {
            $components[$key] = $comparable
                ? $this->delta((float) $before?->getAttribute($attribute), (float) $after?->getAttribute($attribute))
                : $this->delta(null, null);
        }

        return [
            'overall_score' => $comparable
                ? $this->delta((float) $before?->overall_score, (float) $after?->overall_score)
                : $this->delta(null, null),
            'confidence_score' => $comparable
                ? $this->delta((float) $before?->confidence_score, (float) $after?->confidence_score)
                : $this->delta(null, null),
            'components' => $components,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $videos
     * @param  Collection<int, array<string, mixed>>  $channels
     * @return array<string, int|float|null>
     */
    private function metrics(Collection $videos, Collection $channels): array
    {
        return [
            'video_count' => $videos->count(),
            'channel_count' => $channels->count(),
            'median_views' => $this->median($videos->pluck('view_count')->all()),
            'median_views_per_day' => $this->median($videos->pluck('views_per_day')->all()),
            'median_reach_ratio' => $this->median($videos->pluck('reach_ratio')->all()),
            'median_engagement_rate' => $this->median($videos->pluck('engagement_rate')->all()),
            'median_subscribers' => $this->median($channels->pluck('subscriber_count')->all()),
        ];
    }

    /**
     * @param  array<string, int|float|null>  $before
     * @param  array<string, int|float|null>  $after
     * @return array<string, array{before: float|null, after: float|null, delta: float|null, percent_change: float|null}>
     */
    private function metricDeltas(array $before, array $after): array
    {
        $deltas = [];

        foreach ($before as $key => $beforeValue) {
            $afterValue = $after[$key] ?? null;
            $deltas[$key] = $this->delta(
                $beforeValue === null ? null : (float) $beforeValue,
                $afterValue === null ? null : (float) $afterValue,
            );
        }

        return $deltas;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @return array<string, mixed>
     */
    private function entityChanges(Collection $before, Collection $after, bool $videos): array
    {
        $newIds = $after->keys()->diff($before->keys());
        $lostIds = $before->keys()->diff($after->keys());
        $retainedIds = $before->keys()->intersect($after->keys());

        return [
            'before_count' => $before->count(),
            'after_count' => $after->count(),
            'new' => $newIds->map(fn (int $id): array => $this->publicEntity($after->get($id), $videos))->values()->all(),
            'lost' => $lostIds->map(fn (int $id): array => $this->publicEntity($before->get($id), $videos))->values()->all(),
            'retained' => $retainedIds->map(function (int $id) use ($before, $after, $videos): array {
                $beforeRow = $before->get($id);
                $afterRow = $after->get($id);
                $identity = $this->publicEntity($afterRow, $videos);

                if ($videos) {
                    $identity['before_rank'] = $beforeRow['result_rank'];
                    $identity['after_rank'] = $afterRow['result_rank'];
                    $identity['views_per_day'] = $this->delta(
                        $this->nullableFloat($beforeRow['views_per_day']),
                        $this->nullableFloat($afterRow['views_per_day']),
                    );
                } else {
                    $identity['subscriber_count'] = $this->delta(
                        $this->nullableFloat($beforeRow['subscriber_count']),
                        $this->nullableFloat($afterRow['subscriber_count']),
                    );
                }

                return $identity;
            })->values()->all(),
            'leading_before' => $this->leading($before, $videos),
            'leading_after' => $this->leading($after, $videos),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @return array{shared_videos: int, union_videos: int, share_percent: float|null}
     */
    private function sampleOverlap(Collection $before, Collection $after): array
    {
        $shared = $before->keys()->intersect($after->keys())->count();
        $union = $before->keys()->merge($after->keys())->unique()->count();

        return [
            'shared_videos' => $shared,
            'union_videos' => $union,
            'share_percent' => $union === 0 ? null : ($shared / $union) * 100,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @return array{retained_videos: int, unchanged_rank_count: int, unchanged_rank_percent: float|null}
     */
    private function rankStability(Collection $before, Collection $after): array
    {
        $retained = $before->keys()->intersect($after->keys());
        $unchanged = $retained->filter(fn (int $id): bool => $before->get($id)['result_rank'] === $after->get($id)['result_rank'])->count();
        $count = $retained->count();

        return [
            'retained_videos' => $count,
            'unchanged_rank_count' => $unchanged,
            'unchanged_rank_percent' => $count === 0 ? null : ($unchanged / $count) * 100,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>
     */
    private function publicEntity(?array $row, bool $video): array
    {
        if ($row === null) {
            return [];
        }

        return $video
            ? [
                'provider_video_id' => $row['provider_video_id'],
                'title' => $row['title'],
                'result_rank' => $row['result_rank'],
                'view_count' => $row['view_count'],
                'views_per_day' => $row['views_per_day'],
            ]
            : [
                'provider_channel_id' => $row['provider_channel_id'],
                'title' => $row['title'],
                'subscriber_count' => $row['subscriber_count'],
                'subscriber_count_hidden' => $row['subscriber_count_hidden'],
            ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entities
     * @return list<array<string, mixed>>
     */
    private function leading(Collection $entities, bool $videos): array
    {
        $metric = $videos ? 'views_per_day' : 'subscriber_count';

        return array_values($entities
            ->sortByDesc(fn (array $entity): float => $entity[$metric] === null ? -1 : (float) $entity[$metric])
            ->take(5)
            ->map(fn (array $entity): array => $this->publicEntity($entity, $videos))
            ->values()
            ->all());
    }

    /** @param array<int, mixed> $values */
    private function median(array $values): ?float
    {
        $numbers = array_values(array_map(
            fn (mixed $value): float => (float) $value,
            array_filter($values, fn (mixed $value): bool => $value !== null),
        ));

        if ($numbers === []) {
            return null;
        }

        sort($numbers, SORT_NUMERIC);
        $middle = intdiv(count($numbers), 2);

        return count($numbers) % 2 === 0
            ? ($numbers[$middle - 1] + $numbers[$middle]) / 2
            : $numbers[$middle];
    }

    /** @return array{before: float|null, after: float|null, delta: float|null, percent_change: float|null} */
    private function delta(?float $before, ?float $after): array
    {
        return [
            'before' => $before,
            'after' => $after,
            'delta' => $before === null || $after === null ? null : $after - $before,
            'percent_change' => $before === null || $after === null || $before == 0.0
                ? null
                : (($after - $before) / abs($before)) * 100,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
