<?php

namespace App\Domain\Discovery\ReadModels;

use App\Domain\Discovery\Data\DiscoveryObservation;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\DiscoveryRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class CollectDiscoveryObservations
{
    /** @return list<DiscoveryObservation> */
    public function handle(DiscoveryRun $run): array
    {
        $samplePerSeed = max(1, min((int) ($run->parameters['sample_per_seed'] ?? 25), 50));

        /** @var Collection<int, stdClass> $records */
        $records = DB::table('discovery_seeds as seed')
            ->join('research_runs as research_run', 'research_run.id', '=', 'seed.research_run_id')
            ->join('video_snapshots as snapshot', 'snapshot.research_run_id', '=', 'research_run.id')
            ->join('videos as video', 'video.id', '=', 'snapshot.video_id')
            ->join('channels as channel', 'channel.id', '=', 'video.channel_id')
            ->join('research_run_videos as membership', function ($join): void {
                $join->on('membership.research_run_id', '=', 'research_run.id')
                    ->on('membership.video_id', '=', 'video.id');
            })
            ->where('seed.discovery_run_id', $run->id)
            ->where('research_run.user_id', $run->user_id)
            ->where('research_run.status', ResearchRunStatus::Completed->value)
            ->orderBy('seed.id')
            ->orderBy('membership.result_rank')
            ->get([
                'seed.id as seed_id',
                'seed.seed_query',
                'video.provider_video_id',
                'channel.provider_channel_id',
                'video.title',
                'video.published_at',
                'snapshot.view_count',
                'snapshot.views_per_day',
                'snapshot.views_to_subscribers_ratio',
            ]);

        return array_values($records
            ->groupBy(fn (stdClass $record): int => (int) $record->seed_id)
            ->flatMap(fn (Collection $seedRecords): Collection => $seedRecords->take($samplePerSeed))
            ->map(fn (stdClass $record): DiscoveryObservation => new DiscoveryObservation(
                seedId: (int) $record->seed_id,
                seedQuery: (string) $record->seed_query,
                providerVideoId: (string) $record->provider_video_id,
                providerChannelId: (string) $record->provider_channel_id,
                title: (string) $record->title,
                publishedAt: CarbonImmutable::parse((string) $record->published_at),
                viewCount: $record->view_count !== null ? (int) $record->view_count : null,
                viewsPerDay: $record->views_per_day !== null ? (float) $record->views_per_day : null,
                reachRatio: $record->views_to_subscribers_ratio !== null ? (float) $record->views_to_subscribers_ratio : null,
            ))
            ->values()
            ->all());
    }
}
