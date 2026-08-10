<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;

class SyncResearchCollectionRun
{
    public function handle(ResearchRun $researchRun): void
    {
        $collectionRun = $researchRun->collectionRun;

        if ($collectionRun === null || $collectionRun->status->isTerminal()) {
            return;
        }

        $status = $this->statusFor($researchRun);
        $attributes = [
            'status' => $status,
            'processed_count' => $researchRun->enriched_result_count,
            'progress_percent' => $status === CollectionRunStatus::Completed
                ? 100
                : min(99, max(0, $researchRun->progress_percent)),
            'warnings' => $researchRun->collection_warnings,
        ];

        if ($status === CollectionRunStatus::Collecting) {
            $attributes['started_at'] = $collectionRun->started_at ?? $researchRun->started_at ?? now();
        }

        if ($status === CollectionRunStatus::Completed) {
            $attributes['started_at'] = $collectionRun->started_at ?? $researchRun->started_at ?? now();
            $attributes['completed_at'] = now();
        }

        if ($status === CollectionRunStatus::Failed) {
            $attributes['failed_at'] = $researchRun->failed_at ?? now();
            $attributes['error_code'] = $researchRun->error_code;
            $attributes['error_message'] = $researchRun->error_message;
        }

        $collectionRun->update($attributes);
    }

    private function statusFor(ResearchRun $run): CollectionRunStatus
    {
        return match ($run->status) {
            ResearchRunStatus::Draft, ResearchRunStatus::Queued => CollectionRunStatus::Queued,
            ResearchRunStatus::Searching, ResearchRunStatus::Enriching => CollectionRunStatus::Collecting,
            ResearchRunStatus::Scoring, ResearchRunStatus::Completed => CollectionRunStatus::Completed,
            ResearchRunStatus::Failed => $run->error_code === 'research_scoring_failed'
                ? CollectionRunStatus::Completed
                : CollectionRunStatus::Failed,
        };
    }
}
