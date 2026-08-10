<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Collection\Data\CollectionChannelObservation;
use App\Models\AnalyzerRun;
use DomainException;

final class PinAnalyzerChannelObservation
{
    public function handle(AnalyzerRun $run, CollectionChannelObservation $observation): AnalyzerRun
    {
        if ($run->target_kind !== 'channel' || $observation->channel->provider_channel_id !== $run->target_provider_id) {
            throw new DomainException('The channel observation does not match the Analyzer target.');
        }

        $run->update([
            'channel_id' => $observation->channel->id,
            'channel_snapshot_id' => $observation->channelSnapshot->id,
        ]);

        return $run->fresh() ?? $run;
    }
}
