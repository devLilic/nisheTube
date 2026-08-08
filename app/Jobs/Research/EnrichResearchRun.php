<?php

namespace App\Jobs\Research;

use App\Domain\Catalog\Actions\PersistResearchRunEnrichmentBatch;
use App\Domain\Research\Actions\AppendResearchRunWarning;
use App\Domain\Research\Actions\FailResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Domain\YouTube\Services\YouTubeIdBatcher;
use App\Models\ResearchRun;
use App\Models\ResearchRunSearchResult;
use App\Models\VideoSnapshot;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class EnrichResearchRun implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $researchRunId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("research-run:{$this->researchRunId}:enrichment"))
                ->releaseAfter(5)
                ->expireAfter(360),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->researchRunId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(
        VideoResearchProvider $provider,
        YouTubeIdBatcher $batcher,
        PersistResearchRunEnrichmentBatch $persistBatch,
        TransitionResearchRun $transition,
        AppendResearchRunWarning $appendWarning,
        FailResearchRun $failRun,
    ): void {
        $run = ResearchRun::query()->find($this->researchRunId);

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        if ($run->status === ResearchRunStatus::Searching) {
            $run = $transition->handle($run, ResearchRunStatus::Enriching);
        } elseif ($run->status !== ResearchRunStatus::Enriching) {
            return;
        }

        $remainingResults = $this->remainingSearchResults($run);
        $resultsByVideoId = [];

        foreach ($remainingResults as $result) {
            $resultsByVideoId[$result->provider_video_id] = $result;
        }

        $context = new ProviderRequestContext(
            userId: $run->user_id,
            researchRunId: $run->id,
        );

        foreach ($batcher->batches(array_keys($resultsByVideoId)) as $videoIds) {
            $batchResults = [];

            foreach ($videoIds as $videoId) {
                $batchResults[] = $resultsByVideoId[$videoId];
            }

            try {
                $videoBatch = $provider->fetchVideos(new YouTubeIdBatchRequest($videoIds, $context));
                $channelIds = array_values(array_unique(array_map(
                    fn ($video): string => $video->channelId,
                    $videoBatch->videos,
                )));
                $channelBatch = $channelIds === []
                    ? new ChannelDetailsBatch([], $videoBatch->collectedAt)
                    : $provider->fetchChannels(new YouTubeIdBatchRequest($channelIds, $context));
            } catch (YouTubeProviderException $exception) {
                $this->handleProviderFailure($run, $exception, $transition, $appendWarning, $failRun);

                return;
            }

            $run = $persistBatch->handle($run, $batchResults, $videoBatch, $channelBatch);

            if ($run->status !== ResearchRunStatus::Enriching) {
                return;
            }
        }

        $transition->handle($run->fresh() ?? $run, ResearchRunStatus::Scoring);
    }

    public function failed(?Throwable $exception): void
    {
        app(FailResearchRun::class)->handle(
            $this->researchRunId,
            $exception ?? new \RuntimeException('The enrichment handoff job failed.'),
        );
    }

    /** @return list<ResearchRunSearchResult> */
    private function remainingSearchResults(ResearchRun $run): array
    {
        $enrichedProviderIds = VideoSnapshot::query()
            ->join('videos', 'videos.id', '=', 'video_snapshots.video_id')
            ->where('video_snapshots.research_run_id', $run->id)
            ->pluck('videos.provider_video_id')
            ->all();

        return array_values($run->searchResults()
            ->when(
                $enrichedProviderIds !== [],
                fn ($query) => $query->whereNotIn('provider_video_id', $enrichedProviderIds),
            )
            ->orderBy('result_rank')
            ->get()
            ->values()
            ->all());
    }

    private function handleProviderFailure(
        ResearchRun $run,
        YouTubeProviderException $exception,
        TransitionResearchRun $transition,
        AppendResearchRunWarning $appendWarning,
        FailResearchRun $failRun,
    ): void {
        $run = $run->fresh() ?? $run;

        if ($exception->providerCode === YouTubeErrorCode::PartialData && $run->enriched_result_count > 0) {
            $run = $appendWarning->handle(
                $run,
                'YouTube returned incomplete enrichment data; the saved video and channel metrics will continue to scoring.',
            );
            $transition->handle($run, ResearchRunStatus::Scoring);

            return;
        }

        if ($exception->isRetryable()) {
            throw $exception;
        }

        $failRun->handle($run->id, $exception);
    }
}
