<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Data\CollectionObservationBatch;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;
use App\Models\ResearchRunSearchResult;
use App\Models\ResearchRunVideo;
use Illuminate\Support\Facades\DB;

final readonly class PinResearchRunObservationBatch
{
    public function __construct(private SyncResearchCollectionRun $syncCollectionRun) {}

    /**
     * @param  list<ResearchRunSearchResult>  $searchResults
     * @param  list<string>  $warnings
     */
    public function handle(
        ResearchRun $run,
        array $searchResults,
        CollectionObservationBatch $batch,
        array $warnings = [],
    ): ResearchRun {
        return DB::transaction(function () use ($run, $searchResults, $batch, $warnings): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status !== ResearchRunStatus::Enriching) {
                return $lockedRun;
            }

            foreach ($searchResults as $searchResult) {
                $observation = $batch->observation($searchResult->provider_video_id);

                if ($observation === null) {
                    continue;
                }

                $membership = ResearchRunVideo::query()->firstOrCreate(
                    [
                        'research_run_id' => $lockedRun->id,
                        'video_id' => $observation->video->id,
                    ],
                    [
                        'result_rank' => $searchResult->result_rank,
                        'page_number' => $searchResult->page_number,
                        'provider_order' => $searchResult->provider_order,
                        'matched_query_metadata' => [
                            'search_title' => $searchResult->title,
                            'search_channel_id' => $searchResult->provider_channel_id,
                            'search_published_at' => $searchResult->published_at->toISOString(),
                        ],
                    ],
                );
                $membership->pinSources($observation->videoSnapshot, $observation->channelSnapshot);
            }

            $enrichedCount = $lockedRun->videoMemberships()
                ->whereNotNull('video_snapshot_id')
                ->count();
            $sampleSize = max(1, $lockedRun->collected_result_count);
            $progress = min(89, max(50, 50 + (int) floor(($enrichedCount / $sampleSize) * 40)));
            $collectionWarnings = array_values(array_unique([
                ...($lockedRun->collection_warnings ?? []),
                ...$warnings,
            ]));

            $lockedRun->update([
                'enriched_result_count' => $enrichedCount,
                'progress_percent' => $progress,
                'collection_warnings' => $collectionWarnings === [] ? null : $collectionWarnings,
            ]);
            $this->syncCollectionRun->handle($lockedRun);

            return $lockedRun;
        });
    }
}
