<?php

namespace App\Domain\Scoring\Actions;

use App\Domain\Scoring\Data\ScoringInput;
use App\Domain\Scoring\Data\ScoringVideoInput;
use App\Domain\Scoring\Services\NicheOpportunityV1;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class BuildScoringInput
{
    public function handle(ResearchRun $run): ScoringInput
    {
        /** @var Collection<int, stdClass> $records */
        $records = DB::table('video_snapshots as video_snapshot')
            ->join('research_run_videos as membership', 'membership.video_snapshot_id', '=', 'video_snapshot.id')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->leftJoin('channel_snapshots as channel_snapshot', 'channel_snapshot.id', '=', 'membership.channel_snapshot_id')
            ->where('membership.research_run_id', $run->id)
            ->orderBy('membership.result_rank')
            ->select([
                'video.id as video_id',
                'video.channel_id',
                'video.is_short',
                'video.category_id',
                'video_snapshot.view_count',
                'video_snapshot.like_count',
                'video_snapshot.comment_count',
                'video_snapshot.views_per_day',
                'video_snapshot.views_to_subscribers_ratio',
                'video_snapshot.age_seconds',
                'channel_snapshot.subscriber_count',
                'channel_snapshot.subscriber_count_hidden',
                'channel_snapshot.video_count as channel_video_count',
                'channel_snapshot.metadata as channel_metadata',
                'channel_snapshot.collected_at as channel_collected_at',
            ])
            ->get();

        $videos = array_values($records
            ->map(fn (stdClass $record): ScoringVideoInput => new ScoringVideoInput(
                videoId: (int) $record->video_id,
                channelId: (int) $record->channel_id,
                viewCount: $this->nullableInt($record->view_count),
                likeCount: $this->nullableInt($record->like_count),
                commentCount: $this->nullableInt($record->comment_count),
                viewsPerDay: $this->nullableFloat($record->views_per_day),
                viewsToSubscribersRatio: $this->nullableFloat($record->views_to_subscribers_ratio),
                ageDays: $record->age_seconds !== null ? ((int) $record->age_seconds) / 86400 : null,
                subscriberCount: $this->nullableInt($record->subscriber_count),
                subscriberCountHidden: (bool) ($record->subscriber_count_hidden ?? false),
                isShort: $record->is_short !== null ? (bool) $record->is_short : null,
                categoryId: $record->category_id !== null ? (string) $record->category_id : null,
                lifetimeUploadsPerMonth: $this->lifetimeUploadsPerMonth($record),
            ))
            ->all());

        return new ScoringInput(
            requestedResultCount: $run->requested_result_count,
            collectedResultCount: $run->collected_result_count,
            enrichedResultCount: $run->enriched_result_count,
            collectionWarnings: $run->collection_warnings ?? [],
            videos: $videos,
            previousMedianViewsPerDay: $this->previousMedianViewsPerDay($run),
        );
    }

    private function previousMedianViewsPerDay(ResearchRun $run): ?float
    {
        $scores = OpportunityScore::query()
            ->with('researchRun')
            ->where('formula_version', NicheOpportunityV1::VERSION)
            ->whereHas('researchRun', fn ($query) => $query
                ->where('research_query_id', $run->research_query_id)
                ->where('id', '<', $run->id))
            ->orderByDesc('research_run_id')
            ->get();

        foreach ($scores as $score) {
            $previousRun = $score->researchRun;

            if ($previousRun->market_key !== $run->market_key || $previousRun->parameters !== $run->parameters) {
                continue;
            }

            $value = $score->input_summary['statistics']['median_views_per_day'] ?? null;

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    private function lifetimeUploadsPerMonth(stdClass $record): ?float
    {
        $videoCount = $this->nullableInt($record->channel_video_count);
        $metadata = $this->metadata($record->channel_metadata);
        $publishedAt = isset($metadata['published_at']) && is_string($metadata['published_at'])
            ? CarbonImmutable::parse($metadata['published_at'])
            : null;
        $collectedAt = $record->channel_collected_at !== null
            ? CarbonImmutable::parse((string) $record->channel_collected_at)
            : null;

        if ($videoCount === null || $publishedAt === null || $collectedAt === null || $publishedAt >= $collectedAt) {
            return null;
        }

        $monthsActive = max(($publishedAt->diffInSeconds($collectedAt) / 86400) / 30.4375, 1.0);

        return $videoCount / $monthsActive;
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata)) {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
