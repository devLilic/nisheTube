<?php

namespace App\Domain\Exports\ReadModels;

use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ChannelSnapshot;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\ResearchRunVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use JsonException;

final class BuildResearchRunExportDataset
{
    public function __construct(private readonly ResearchExportColumns $columns) {}

    /**
     * @param  list<string>  $publicIds
     * @param  list<string>|null  $columnKeys
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function handle(User $user, array $publicIds, ?array $columnKeys = null): ExportDataset
    {
        $selectedColumns = $this->columns->validate($columnKeys ?? $this->columns->all());
        $runs = ResearchRun::query()
            ->whereIn('public_id', $publicIds)
            ->with([
                'opportunityScores' => fn ($query) => $query
                    ->latest('calculated_at')
                    ->latest('id'),
                'videos' => fn ($query) => $query
                    ->with('channel')
                    ->orderByPivot('result_rank'),
                'videoSnapshots',
                'channelSnapshots',
            ])
            ->get();

        if ($runs->count() !== count($publicIds)) {
            throw new DomainException('One or more selected research runs are no longer available.');
        }

        if ($runs->contains(fn (ResearchRun $run): bool => $run->user_id !== $user->id)) {
            throw new AuthorizationException;
        }

        if ($runs->contains(fn (ResearchRun $run): bool => $run->status !== ResearchRunStatus::Completed)) {
            throw new DomainException('Only completed research runs can be exported.');
        }

        $order = array_flip($publicIds);
        $runs = $runs->sortBy(fn (ResearchRun $run): int => $order[$run->public_id])->values();
        $rows = [];

        foreach ($runs as $run) {
            $score = $run->opportunityScores->first();
            $videoSnapshots = $run->videoSnapshots->keyBy('video_id');
            $channelSnapshots = $run->channelSnapshots->keyBy('channel_id');

            if ($run->videos->isEmpty()) {
                $rows[] = $this->project($this->row($run, $score, null, null, null), $selectedColumns);

                continue;
            }

            foreach ($run->videos as $video) {
                $rows[] = $this->project($this->row(
                    $run,
                    $score,
                    $video,
                    $videoSnapshots->get($video->id),
                    $channelSnapshots->get($video->channel_id),
                ), $selectedColumns);
            }
        }

        return new ExportDataset($this->columns->labels($selectedColumns), $rows);
    }

    /** @return array<string, bool|float|int|string|null> */
    private function row(
        ResearchRun $run,
        ?OpportunityScore $score,
        ?Video $video,
        ?VideoSnapshot $videoSnapshot,
        ?ChannelSnapshot $channelSnapshot,
    ): array {
        $channel = $video?->channel;
        $pivot = $video?->getRelation('pivot');
        $rank = $pivot instanceof ResearchRunVideo ? $pivot->result_rank : null;

        return [
            'run_id' => $run->public_id, 'query' => $run->query_text, 'market' => $run->market_key,
            'region' => $run->region_code, 'relevance_language' => $run->relevance_language,
            'collection_parameters' => $this->json($run->parameters), 'requested_results' => $run->requested_result_count,
            'collected_results' => $run->collected_result_count, 'collection_warnings' => $this->json($run->collection_warnings ?? []),
            'run_completed_at' => $run->completed_at?->utc()->toIso8601String(), 'score_formula' => $score?->formula_version,
            'score_calculated_at' => $score?->calculated_at->utc()->toIso8601String(),
            'opportunity_score' => $score === null ? null : (float) $score->overall_score,
            'confidence_score' => $score === null ? null : (float) $score->confidence_score,
            'score_warnings' => $this->json($score === null ? [] : $score->warnings), 'video_id' => $video?->provider_video_id,
            'video_title' => $video?->title, 'video_url' => $video === null ? null : 'https://www.youtube.com/watch?v='.$video->provider_video_id,
            'video_published_at' => $video?->published_at->utc()->toIso8601String(), 'result_rank' => $rank === null ? null : (int) $rank,
            'video_metrics_collected_at' => $videoSnapshot?->collected_at->utc()->toIso8601String(), 'views' => $videoSnapshot?->view_count,
            'likes' => $videoSnapshot?->like_count, 'comments' => $videoSnapshot?->comment_count,
            'views_per_day' => $videoSnapshot?->views_per_day === null ? null : (float) $videoSnapshot->views_per_day,
            'views_to_subscribers_ratio' => $videoSnapshot?->views_to_subscribers_ratio === null ? null : (float) $videoSnapshot->views_to_subscribers_ratio,
            'channel_id' => $channel?->provider_channel_id, 'channel_title' => $channel?->title,
            'channel_url' => $channel === null ? null : 'https://www.youtube.com/channel/'.$channel->provider_channel_id,
            'channel_metrics_collected_at' => $channelSnapshot?->collected_at->utc()->toIso8601String(),
            'subscribers' => $channelSnapshot?->subscriber_count, 'subscribers_hidden' => $channelSnapshot?->subscriber_count_hidden,
            'channel_views' => $channelSnapshot?->view_count, 'channel_videos' => $channelSnapshot?->video_count,
        ];
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $row
     * @param  list<string>  $columns
     * @return list<bool|float|int|string|null>
     */
    private function project(array $row, array $columns): array
    {
        return array_map(fn (string $column): bool|float|int|string|null => $row[$column], $columns);
    }

    /**
     * @param  array<array-key, mixed>  $value
     *
     * @throws JsonException
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
