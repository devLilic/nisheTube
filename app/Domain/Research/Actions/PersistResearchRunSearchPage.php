<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;
use LogicException;

class PersistResearchRunSearchPage
{
    public function handle(
        ResearchRun $run,
        int $pageNumber,
        ?string $requestPageToken,
        VideoSearchPage $page,
    ): ResearchRun {
        return DB::transaction(function () use ($run, $pageNumber, $requestPageToken, $page): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status !== ResearchRunStatus::Searching) {
                return $lockedRun;
            }

            if ($lockedRun->searchPages()->where('page_number', $pageNumber)->exists()) {
                return $lockedRun;
            }

            $lastPage = $lockedRun->searchPages()->orderByDesc('page_number')->first();
            $expectedPageNumber = $lastPage === null ? 1 : $lastPage->page_number + 1;
            $expectedToken = $lastPage === null ? null : $lastPage->next_page_token;

            if ($pageNumber !== $expectedPageNumber || $requestPageToken !== $expectedToken) {
                throw new LogicException('Research search pages must be persisted sequentially.');
            }

            $lockedRun->searchPages()->create([
                'page_number' => $pageNumber,
                'request_page_token' => $requestPageToken,
                'next_page_token' => $page->nextPageToken,
                'result_count' => count($page->results),
                'approximate_total_results' => $page->approximateTotalResults,
                'warnings' => $page->warnings === [] ? null : $page->warnings,
            ]);

            foreach ($page->results as $index => $result) {
                $providerOrder = $index + 1;

                $lockedRun->searchResults()->firstOrCreate(
                    ['provider_video_id' => $result->videoId],
                    [
                        'provider_channel_id' => $result->channelId,
                        'title' => $result->title,
                        'published_at' => $result->publishedAt,
                        'result_rank' => (($pageNumber - 1) * 50) + $providerOrder,
                        'page_number' => $pageNumber,
                        'provider_order' => $providerOrder,
                    ],
                );
            }

            $collectedCount = $lockedRun->searchResults()->count();
            $progress = max(
                5,
                min(49, (int) floor(($collectedCount / $lockedRun->requested_result_count) * 50)),
            );
            $warnings = array_values(array_unique([
                ...($lockedRun->collection_warnings ?? []),
                ...$page->warnings,
            ]));

            $lockedRun->update([
                'collected_result_count' => $collectedCount,
                'progress_percent' => $progress,
                'collection_warnings' => $warnings === [] ? null : $warnings,
            ]);

            return $lockedRun;
        });
    }
}
