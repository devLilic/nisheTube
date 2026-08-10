<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Data\CollectionChannelObservation;
use App\Domain\Collection\Data\CollectionObservation;
use App\Domain\Collection\Data\CollectionObservationBatch;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Collection\Services\CollectionFreshnessWindow;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\CollectionRun;
use App\Models\VideoSnapshot;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ResolveReusableObservationSources
{
    public function __construct(private CollectionFreshnessWindow $freshnessWindow) {}

    /** @param list<string> $providerVideoIds */
    public function handle(CollectionRun $run, array $providerVideoIds): CollectionObservationBatch
    {
        $providerVideoIds = array_values(array_unique(array_filter(array_map(
            static fn (string $id): string => trim($id),
            $providerVideoIds,
        ))));

        if ($run->cache_policy !== CollectionCachePolicy::AllowFreshCache || $providerVideoIds === []) {
            return new CollectionObservationBatch([]);
        }

        $cutoff = Date::now()->subSeconds($this->freshnessWindow->for($run));
        $rows = DB::table('video_snapshots as video_source')
            ->join('videos as video', 'video.id', '=', 'video_source.video_id')
            ->join('collection_runs as source_run', 'source_run.id', '=', 'video_source.collection_run_id')
            ->leftJoin('channel_snapshots as channel_source', function ($join): void {
                $join->on('channel_source.collection_run_id', '=', 'video_source.collection_run_id')
                    ->on('channel_source.channel_id', '=', 'video.channel_id');
            })
            ->where('source_run.user_id', $run->user_id)
            ->where('source_run.provider', $run->provider)
            ->where('source_run.status', CollectionRunStatus::Completed->value)
            ->where('source_run.id', '!=', $run->id)
            ->where('video_source.collected_at', '>=', $cutoff)
            ->whereIn('video.provider_video_id', $providerVideoIds)
            ->orderByDesc('video_source.collected_at')
            ->orderByDesc('video_source.id')
            ->get([
                'video.provider_video_id',
                'video_source.id as video_snapshot_id',
                'channel_source.id as channel_snapshot_id',
            ]);

        $selected = [];

        foreach ($rows as $row) {
            $providerVideoId = (string) $row->provider_video_id;
            $selected[$providerVideoId] ??= $row;
        }

        $videoSnapshots = VideoSnapshot::query()
            ->with('video')
            ->whereIn('id', array_map(
                static fn (object $row): int => (int) $row->video_snapshot_id,
                array_values($selected),
            ))
            ->get()
            ->keyBy('id');
        $channelSnapshotIds = array_values(array_filter(array_map(
            static fn (object $row): ?int => $row->channel_snapshot_id === null
                ? null
                : (int) $row->channel_snapshot_id,
            array_values($selected),
        )));
        $channelSnapshots = ChannelSnapshot::query()
            ->whereIn('id', $channelSnapshotIds)
            ->get()
            ->keyBy('id');
        $observations = [];

        foreach ($selected as $providerVideoId => $row) {
            $videoSnapshot = $videoSnapshots->get((int) $row->video_snapshot_id);

            if ($videoSnapshot === null) {
                continue;
            }

            $channelSnapshot = $row->channel_snapshot_id === null
                ? null
                : $channelSnapshots->get((int) $row->channel_snapshot_id);
            $observations[$providerVideoId] = new CollectionObservation(
                video: $videoSnapshot->video,
                videoSnapshot: $videoSnapshot,
                channelSnapshot: $channelSnapshot,
            );
        }

        return new CollectionObservationBatch($observations);
    }

    /** @param list<string> $providerChannelIds */
    public function handleChannels(CollectionRun $run, array $providerChannelIds): CollectionObservationBatch
    {
        $providerChannelIds = array_values(array_unique(array_filter(array_map(
            static fn (string $id): string => trim($id),
            $providerChannelIds,
        ))));

        if ($run->cache_policy !== CollectionCachePolicy::AllowFreshCache || $providerChannelIds === []) {
            return new CollectionObservationBatch([]);
        }

        $cutoff = Date::now()->subSeconds($this->freshnessWindow->for($run));
        $snapshots = ChannelSnapshot::query()
            ->select('channel_snapshots.*')
            ->join('channels', 'channels.id', '=', 'channel_snapshots.channel_id')
            ->join('collection_runs', 'collection_runs.id', '=', 'channel_snapshots.collection_run_id')
            ->where('collection_runs.user_id', $run->user_id)
            ->where('collection_runs.provider', $run->provider)
            ->where('collection_runs.status', CollectionRunStatus::Completed->value)
            ->where('collection_runs.id', '!=', $run->id)
            ->where('channel_snapshots.collected_at', '>=', $cutoff)
            ->whereIn('channels.provider_channel_id', $providerChannelIds)
            ->with('channel')
            ->orderByDesc('channel_snapshots.collected_at')
            ->orderByDesc('channel_snapshots.id')
            ->get()
            ->unique('channel_id');
        $observations = [];

        foreach ($snapshots as $snapshot) {
            $channel = $snapshot->channel;

            if ($channel instanceof Channel) {
                $observations[$channel->provider_channel_id] = new CollectionChannelObservation($channel, $snapshot);
            }
        }

        return new CollectionObservationBatch([], $observations);
    }
}
