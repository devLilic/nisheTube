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
            ->join('research_run_videos as membership', 'membership.research_run_id', '=', 'research_run.id')
            ->join('video_snapshots as snapshot', 'snapshot.id', '=', 'membership.video_snapshot_id')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->join('channels as channel', 'channel.id', '=', 'video.channel_id')
            ->where('seed.discovery_run_id', $run->id)
            ->where('research_run.user_id', $run->user_id)
            ->where('research_run.status', ResearchRunStatus::Completed->value)
            ->orderBy('seed.id')
            ->orderBy('membership.result_rank')
            ->get([
                'seed.id as seed_id',
                'seed.seed_query',
                'research_run.public_id as research_run_public_id',
                'video.provider_video_id',
                'channel.provider_channel_id',
                'video.title',
                'video.published_at',
                'snapshot.view_count',
                'snapshot.views_per_day',
                'snapshot.views_to_subscribers_ratio',
            ]);

        $analyzerRuns = DB::table('analyzer_runs')
            ->where('user_id', $run->user_id)
            ->where('origin_kind', 'search')
            ->whereIn('origin_reference', $records->pluck('research_run_public_id')->unique()->all())
            ->whereIn('target_provider_id', $records->pluck('provider_video_id')->unique()->all())
            ->where('target_kind', 'video')
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->get(['public_id', 'origin_reference', 'target_provider_id'])
            ->unique(fn (stdClass $record): string => $record->origin_reference.'|'.$record->target_provider_id)
            ->keyBy(fn (stdClass $record): string => $record->origin_reference.'|'.$record->target_provider_id);
        $topicsByAnalyzerRun = DB::table('semantic_classifications as classification')
            ->join('semantic_topic_profiles as profile', 'profile.id', '=', 'classification.semantic_topic_profile_id')
            ->join('analyzer_runs as analyzer', 'analyzer.id', '=', 'profile.analyzer_run_id')
            ->where('profile.user_id', $run->user_id)
            ->whereIn('analyzer.public_id', $analyzerRuns->pluck('public_id')->all())
            ->whereIn('profile.status', ['complete', 'partial'])
            ->where('classification.kind', 'topic')
            ->orderBy('classification.position')
            ->get(['analyzer.public_id as analyzer_public_id', 'classification.label'])
            ->groupBy('analyzer_public_id')
            ->map(fn (Collection $topics): array => $topics->pluck('label')->unique()->take(8)->values()->all());

        return array_values($records
            ->groupBy(fn (stdClass $record): int => (int) $record->seed_id)
            ->flatMap(fn (Collection $seedRecords): Collection => $seedRecords->take($samplePerSeed))
            ->map(function (stdClass $record) use ($analyzerRuns, $topicsByAnalyzerRun): DiscoveryObservation {
                $analyzerRun = $analyzerRuns->get($record->research_run_public_id.'|'.$record->provider_video_id);

                return new DiscoveryObservation(
                    seedId: (int) $record->seed_id,
                    seedQuery: (string) $record->seed_query,
                    providerVideoId: (string) $record->provider_video_id,
                    providerChannelId: (string) $record->provider_channel_id,
                    title: (string) $record->title,
                    publishedAt: CarbonImmutable::parse((string) $record->published_at),
                    viewCount: $record->view_count !== null ? (int) $record->view_count : null,
                    viewsPerDay: $record->views_per_day !== null ? (float) $record->views_per_day : null,
                    reachRatio: $record->views_to_subscribers_ratio !== null ? (float) $record->views_to_subscribers_ratio : null,
                    analyzerRunPublicId: $analyzerRun?->public_id,
                    inferredTopics: $analyzerRun === null ? [] : array_values(array_filter(
                        $topicsByAnalyzerRun->get($analyzerRun->public_id) ?? [],
                        fn (mixed $topic): bool => is_string($topic),
                    )),
                );
            })
            ->values()
            ->all());
    }
}
