<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Models\AnalyzerRun;
use DomainException;

final class TransitionAnalyzerRun
{
    public function handle(
        AnalyzerRun $run,
        AnalyzerRunStatus $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): AnalyzerRun {
        if (! $this->allowed($run->status, $status)) {
            throw new DomainException("Cannot transition Analyzer run from {$run->status->value} to {$status->value}.");
        }

        $progress = match ($status) {
            AnalyzerRunStatus::Queued => 0,
            AnalyzerRunStatus::FetchingVideo => 20,
            AnalyzerRunStatus::FetchingChannel => 50,
            AnalyzerRunStatus::LoadingRecentVideos => 60,
            AnalyzerRunStatus::CalculatingMetrics => 75,
            AnalyzerRunStatus::SavingAnalysis => 90,
            AnalyzerRunStatus::Completed => 100,
            AnalyzerRunStatus::Failed => $run->progress_percent,
        };
        $attributes = ['status' => $status, 'progress_percent' => $progress];

        if (in_array($status, [AnalyzerRunStatus::FetchingVideo, AnalyzerRunStatus::FetchingChannel], true)) {
            $attributes['started_at'] = $run->started_at ?? now();
        }

        if ($status === AnalyzerRunStatus::Completed) {
            $attributes['completed_at'] = now();
            $attributes['calculated_at'] = $run->calculated_at ?? now();
        }

        if ($status === AnalyzerRunStatus::Failed) {
            $attributes['failed_at'] = now();
            $attributes['error_code'] = $errorCode ?? 'analyzer_collection_failed';
            $attributes['error_message'] = $errorMessage ?? 'The Analyzer collection could not be completed.';
        }

        $run->update($attributes);
        $this->syncCollectionRun($run->fresh() ?? $run);

        return $run->fresh() ?? $run;
    }

    private function allowed(AnalyzerRunStatus $from, AnalyzerRunStatus $to): bool
    {
        if ($to === AnalyzerRunStatus::Failed) {
            return ! $from->isTerminal();
        }

        return match ($from) {
            AnalyzerRunStatus::Queued => in_array($to, [AnalyzerRunStatus::FetchingVideo, AnalyzerRunStatus::FetchingChannel], true),
            AnalyzerRunStatus::FetchingVideo => in_array($to, [AnalyzerRunStatus::FetchingChannel, AnalyzerRunStatus::LoadingRecentVideos], true),
            AnalyzerRunStatus::FetchingChannel => $to === AnalyzerRunStatus::LoadingRecentVideos,
            AnalyzerRunStatus::LoadingRecentVideos => $to === AnalyzerRunStatus::CalculatingMetrics,
            AnalyzerRunStatus::CalculatingMetrics => $to === AnalyzerRunStatus::SavingAnalysis,
            AnalyzerRunStatus::SavingAnalysis => $to === AnalyzerRunStatus::Completed,
            default => false,
        };
    }

    private function syncCollectionRun(AnalyzerRun $run): void
    {
        $collectionRun = $run->collectionRun;

        if ($collectionRun->status->isTerminal()) {
            return;
        }

        $attributes = [
            'status' => $run->status === AnalyzerRunStatus::Completed
                ? CollectionRunStatus::Completed
                : ($run->status === AnalyzerRunStatus::Failed ? CollectionRunStatus::Failed : CollectionRunStatus::Collecting),
            'processed_count' => $run->videoMemberships()->count(),
            'progress_percent' => $run->progress_percent,
            'warnings' => $run->warnings,
        ];

        if ($run->started_at !== null) {
            $attributes['started_at'] = $run->started_at;
        }

        if ($run->status === AnalyzerRunStatus::Completed) {
            $attributes['completed_at'] = $run->completed_at ?? now();
        }

        if ($run->status === AnalyzerRunStatus::Failed) {
            $attributes['failed_at'] = $run->failed_at ?? now();
            $attributes['error_code'] = $run->error_code;
            $attributes['error_message'] = $run->error_message;
        }

        $collectionRun->update($attributes);
    }
}
