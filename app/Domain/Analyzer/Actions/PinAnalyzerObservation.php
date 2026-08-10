<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Data\CollectionObservation;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PinAnalyzerObservation
{
    public function handle(AnalyzerRun $run, CollectionObservation $observation): AnalyzerRun
    {
        if ($observation->video->provider_video_id !== $run->target_provider_id) {
            throw new DomainException('The observation does not match the Analyzer target.');
        }

        return DB::transaction(function () use ($run, $observation): AnalyzerRun {
            AnalyzerRunVideo::query()->firstOrCreate([
                'analyzer_run_id' => $run->id,
                'video_id' => $observation->video->id,
                'role' => AnalyzerVideoRole::Anchor,
            ], [
                'video_snapshot_id' => $observation->videoSnapshot->id,
                'channel_snapshot_id' => $observation->channelSnapshot?->id,
                'source_position' => 1,
            ]);

            $run->update([
                'video_id' => $observation->video->id,
                'channel_id' => $observation->video->channel_id,
                'channel_snapshot_id' => $observation->channelSnapshot?->id,
            ]);

            return $run->fresh() ?? $run;
        });
    }
}
