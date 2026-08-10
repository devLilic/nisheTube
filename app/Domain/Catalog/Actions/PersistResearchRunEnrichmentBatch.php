<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Collection\Actions\PersistCollectionObservationBatch;
use App\Domain\Collection\Actions\PinResearchRunObservationBatch;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Models\ResearchRun;
use App\Models\ResearchRunSearchResult;

final readonly class PersistResearchRunEnrichmentBatch
{
    public function __construct(
        private PersistCollectionObservationBatch $persistObservations,
        private PinResearchRunObservationBatch $pinObservations,
    ) {}

    /** @param list<ResearchRunSearchResult> $searchResults */
    public function handle(
        ResearchRun $run,
        array $searchResults,
        VideoDetailsBatch $videoBatch,
        ChannelDetailsBatch $channelBatch,
    ): ResearchRun {
        $observations = $this->persistObservations->handle(
            collectionRun: $run->collectionRun()->firstOrFail(),
            videoBatch: $videoBatch,
            channelBatch: $channelBatch,
            legacyResearchRun: $run,
        );

        return $this->pinObservations->handle(
            run: $run,
            searchResults: $searchResults,
            batch: $observations,
            warnings: [...$videoBatch->warnings, ...$channelBatch->warnings],
        );
    }
}
