<?php

namespace App\Jobs\Analyzer;

use App\Domain\Analyzer\Actions\AppendAnalyzerWarning;
use App\Domain\Analyzer\Actions\CalculateAnalyzerMetrics;
use App\Domain\Analyzer\Actions\CollectAnalyzerRecentCohort;
use App\Domain\Analyzer\Actions\FailAnalyzerRun;
use App\Domain\Analyzer\Actions\PinAnalyzerChannelObservation;
use App\Domain\Analyzer\Actions\PinAnalyzerObservation;
use App\Domain\Analyzer\Actions\ResolveVideoCategory;
use App\Domain\Analyzer\Actions\TransitionAnalyzerRun;
use App\Domain\Analyzer\Actions\UpdateUserEntityObservations;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Actions\PersistCollectionObservationBatch;
use App\Domain\Collection\Actions\ResolveReusableObservationSources;
use App\Domain\YouTube\Contracts\ChannelUploadsProvider;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Jobs\Watchlist\FinalizeWatchlistRefresh;
use App\Models\AnalyzerRun;
use App\Models\WatchlistRefreshRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CollectAnalyzerRun implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $analyzerRunId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("analyzer-run:{$this->analyzerRunId}"))->releaseAfter(5)->expireAfter(240)];
    }

    public function uniqueId(): string
    {
        return (string) $this->analyzerRunId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(
        VideoResearchProvider $provider,
        ChannelUploadsProvider $uploadsProvider,
        ResolveReusableObservationSources $resolveReusableSources,
        PersistCollectionObservationBatch $persistObservationBatch,
        PinAnalyzerObservation $pinObservation,
        PinAnalyzerChannelObservation $pinChannelObservation,
        CalculateAnalyzerMetrics $calculateMetrics,
        CollectAnalyzerRecentCohort $collectRecentCohort,
        UpdateUserEntityObservations $updateObservations,
        ResolveVideoCategory $resolveCategory,
        TransitionAnalyzerRun $transition,
        AppendAnalyzerWarning $appendWarning,
        FailAnalyzerRun $failRun,
    ): void {
        $run = AnalyzerRun::query()->find($this->analyzerRunId);

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        WatchlistRefreshRun::query()
            ->where('analyzer_run_id', $run->id)
            ->where('user_id', $run->user_id)
            ->where('status', 'queued')
            ->update(['status' => 'processing', 'started_at' => now()]);

        try {
            if ($run->status === AnalyzerRunStatus::Queued) {
                $run = $transition->handle(
                    $run,
                    $run->target_kind === 'channel'
                        ? AnalyzerRunStatus::FetchingChannel
                        : AnalyzerRunStatus::FetchingVideo,
                );
            }

            if ($run->target_kind === 'channel' && $run->channel_snapshot_id === null) {
                $cached = $resolveReusableSources->handleChannels($run->collectionRun, [$run->target_provider_id]);
                $observation = $cached->channelObservation($run->target_provider_id);

                if ($observation === null) {
                    $context = new ProviderRequestContext(
                        userId: $run->user_id,
                        collectionRunId: $run->collection_run_id,
                    );
                    $channelBatch = $provider->fetchChannels(new YouTubeIdBatchRequest([$run->target_provider_id], $context));
                    $details = $channelBatch->channels[0] ?? null;

                    if ($details === null || $details->channelId !== $run->target_provider_id) {
                        throw new YouTubeProviderException(YouTubeErrorCode::ChannelNotFound);
                    }

                    $persisted = $persistObservationBatch->handle(
                        $run->collectionRun,
                        new VideoDetailsBatch([], $channelBatch->collectedAt),
                        $channelBatch,
                    );
                    $observation = $persisted->channelObservation($run->target_provider_id);

                    foreach ($channelBatch->warnings as $warning) {
                        $run = $appendWarning->handle($run, $warning);
                    }
                } else {
                    $run = $appendWarning->handle(
                        $run,
                        'A recent owner-scoped channel observation was reused; values retain their original observation time.',
                    );
                }

                if ($observation === null) {
                    throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
                }

                $run = $pinChannelObservation->handle($run, $observation);
            }

            if ($run->target_kind === 'video' && $run->videoMemberships()->doesntExist()) {
                $cached = $resolveReusableSources->handle($run->collectionRun, [$run->target_provider_id]);
                $observation = $cached->observation($run->target_provider_id);

                if ($observation === null) {
                    $context = new ProviderRequestContext(
                        userId: $run->user_id,
                        collectionRunId: $run->collection_run_id,
                    );
                    $videoBatch = $provider->fetchVideos(new YouTubeIdBatchRequest([$run->target_provider_id], $context));
                    $video = $videoBatch->videos[0] ?? null;

                    if ($video === null || $video->videoId !== $run->target_provider_id) {
                        throw new YouTubeProviderException(YouTubeErrorCode::VideoNotFound);
                    }

                    if ($run->status === AnalyzerRunStatus::FetchingVideo) {
                        $run = $transition->handle($run, AnalyzerRunStatus::FetchingChannel);
                    }

                    $channelBatch = $provider->fetchChannels(new YouTubeIdBatchRequest([$video->channelId], $context));
                    $observations = $persistObservationBatch->handle($run->collectionRun, $videoBatch, $channelBatch);
                    $observation = $observations->observation($run->target_provider_id);

                    if ($observation === null) {
                        throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
                    }

                    foreach ([...$videoBatch->warnings, ...$channelBatch->warnings] as $warning) {
                        $run = $appendWarning->handle($run, $warning);
                    }
                } else {
                    $run = $appendWarning->handle(
                        $run,
                        'Recent owner-scoped observations were reused; values retain their original observation time.',
                    );
                }

                $run = $pinObservation->handle($run, $observation);
                $resolveCategory->handle($observation->video);
            }

            if (in_array($run->status, [AnalyzerRunStatus::FetchingVideo, AnalyzerRunStatus::FetchingChannel], true)) {
                $run = $transition->handle($run, AnalyzerRunStatus::LoadingRecentVideos);
            }

            if ($run->status === AnalyzerRunStatus::LoadingRecentVideos) {
                $run = $collectRecentCohort->handle(
                    $run,
                    $uploadsProvider,
                    $provider,
                    $resolveReusableSources,
                    $persistObservationBatch,
                    $appendWarning,
                    $resolveCategory,
                );
                $run = $transition->handle($run, AnalyzerRunStatus::CalculatingMetrics);
            }

            if ($run->status === AnalyzerRunStatus::CalculatingMetrics) {
                $calculateMetrics->handle($run);
                $run->update(['calculated_at' => now()]);
                $run = $transition->handle($run->fresh() ?? $run, AnalyzerRunStatus::SavingAnalysis);
            }

            if ($run->status === AnalyzerRunStatus::SavingAnalysis) {
                $updateObservations->handle($run);
                $transition->handle($run->fresh() ?? $run, AnalyzerRunStatus::Completed);
            }
        } catch (YouTubeProviderException $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }

            $failRun->handle($run->id, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(FailAnalyzerRun::class)->handle(
            $this->analyzerRunId,
            $exception ?? new \RuntimeException('The Analyzer collection job failed.'),
        );

        $refreshId = WatchlistRefreshRun::query()
            ->where('analyzer_run_id', $this->analyzerRunId)
            ->value('id');
        if (is_numeric($refreshId)) {
            FinalizeWatchlistRefresh::dispatch((int) $refreshId);
        }
    }
}
