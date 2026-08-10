<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Models\AnalyzerRun;
use App\Models\UserEntityObservation;

final class UpdateUserEntityObservations
{
    public function handle(AnalyzerRun $run): void
    {
        $memberships = $run->videoMemberships()
            ->with(['videoSnapshot', 'channelSnapshot'])
            ->get();
        $membership = $memberships->firstWhere('role', AnalyzerVideoRole::Anchor);
        $seenAt = now();

        if ($membership !== null) {
            $videoSnapshot = $membership->videoSnapshot;
            $this->updateObservation(
                $run,
                'video',
                $membership->video_id,
                $videoSnapshot->collected_at,
                $videoSnapshot->view_count,
                ['first_video_snapshot_id' => $videoSnapshot->id, 'latest_video_snapshot_id' => $videoSnapshot->id],
                $seenAt,
            );
        }

        foreach ($memberships as $recentMembership) {
            if ($recentMembership->role !== AnalyzerVideoRole::ChannelRecentUpload) {
                continue;
            }

            $recentSnapshot = $recentMembership->videoSnapshot;
            $this->updateObservation(
                $run,
                'video',
                $recentMembership->video_id,
                $recentSnapshot->collected_at,
                $recentSnapshot->view_count,
                [
                    'first_video_snapshot_id' => $recentSnapshot->id,
                    'latest_video_snapshot_id' => $recentSnapshot->id,
                ],
                $seenAt,
            );
        }

        if ($run->channelSnapshot !== null && $run->channel_id !== null) {
            $channelSnapshot = $run->channelSnapshot;
            $this->updateObservation(
                $run,
                'channel',
                $run->channel_id,
                $channelSnapshot->collected_at,
                $channelSnapshot->subscriber_count,
                ['first_channel_snapshot_id' => $channelSnapshot->id, 'latest_channel_snapshot_id' => $channelSnapshot->id],
                $seenAt,
            );
        }
    }

    /** @param array<string, int> $snapshotFields */
    private function updateObservation(
        AnalyzerRun $run,
        string $subjectType,
        int $subjectId,
        mixed $fetchedAt,
        ?int $count,
        array $snapshotFields,
        mixed $seenAt,
    ): void {
        $observation = UserEntityObservation::query()->firstOrCreate([
            'user_id' => $run->user_id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ], [
            'first_seen_at' => $fetchedAt,
            'last_seen_at' => $seenAt,
            'last_fetched_at' => $fetchedAt,
            'first_observed_count' => $count,
            ...$snapshotFields,
        ]);

        if (! $observation->wasRecentlyCreated) {
            $latestField = $subjectType === 'video' ? 'latest_video_snapshot_id' : 'latest_channel_snapshot_id';
            $observation->update([
                'last_seen_at' => $seenAt,
                'last_fetched_at' => max($observation->last_fetched_at, $fetchedAt),
                $latestField => $snapshotFields[$latestField],
            ]);
        }
    }
}
