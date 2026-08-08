<?php

namespace App\Jobs\Research;

use App\Domain\Research\Actions\FailResearchRun;
use App\Domain\Research\Actions\MarkResearchRunSearchComplete;
use App\Domain\Research\Actions\PersistResearchRunSearchPage;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\ResearchRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CollectResearchRunSearch implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $researchRunId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("research-run:{$this->researchRunId}:search"))
                ->releaseAfter(5)
                ->expireAfter(180),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->researchRunId;
    }

    public function handle(
        VideoResearchProvider $provider,
        TransitionResearchRun $transition,
        PersistResearchRunSearchPage $persistPage,
        MarkResearchRunSearchComplete $markComplete,
        FailResearchRun $failRun,
    ): void {
        $run = ResearchRun::query()->find($this->researchRunId);

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        if ($run->status === ResearchRunStatus::Queued) {
            $run = $transition->handle($run, ResearchRunStatus::Searching);
            $run->update(['progress_percent' => max(5, $run->progress_percent)]);
        } elseif ($run->status !== ResearchRunStatus::Searching) {
            return;
        }

        while (true) {
            $run = $run->fresh();

            if ($run === null || $run->status !== ResearchRunStatus::Searching) {
                return;
            }

            $lastPage = $run->searchPages()->orderByDesc('page_number')->first();
            $nextPageNumber = $lastPage === null ? 1 : $lastPage->page_number + 1;
            $maxPages = (int) ceil($run->requested_result_count / 50);

            if ($run->collected_result_count >= $run->requested_result_count) {
                $this->handoffToEnrichment($markComplete->handle($run));

                return;
            }

            if ($lastPage !== null && $lastPage->next_page_token === null) {
                $this->handoffToEnrichment($markComplete->handle(
                    $run,
                    $this->shortSampleWarning($run),
                ));

                return;
            }

            if ($nextPageNumber > $maxPages) {
                $this->handoffToEnrichment($markComplete->handle(
                    $run,
                    $this->shortSampleWarning($run),
                ));

                return;
            }

            $requestPageToken = $lastPage === null ? null : $lastPage->next_page_token;

            if ($requestPageToken !== null && $this->tokenWasAlreadyRequested($run, $requestPageToken)) {
                $this->handoffToEnrichment($markComplete->handle(
                    $run,
                    'YouTube returned a repeated page token, so collection stopped with the results already saved.',
                ));

                return;
            }

            $remaining = $run->requested_result_count - $run->collected_result_count;
            $request = $this->requestForRun(
                $run,
                min(50, $remaining),
                $requestPageToken,
            );

            try {
                $page = $provider->search($request);
            } catch (YouTubeProviderException $exception) {
                if ($exception->providerCode === YouTubeErrorCode::PartialData && $run->collected_result_count > 0) {
                    $this->handoffToEnrichment($markComplete->handle(
                        $run,
                        'YouTube returned incomplete data after partial collection; the saved results will continue to enrichment.',
                    ));

                    return;
                }

                if ($exception->isRetryable()) {
                    throw $exception;
                }

                $failRun->handle($run->id, $exception);

                return;
            }

            $run = $persistPage->handle($run, $nextPageNumber, $requestPageToken, $page);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(FailResearchRun::class)->handle(
            $this->researchRunId,
            $exception ?? new \RuntimeException('The research search job failed.'),
        );
    }

    private function handoffToEnrichment(ResearchRun $run): void
    {
        EnrichResearchRun::dispatch($run->id)->afterCommit();
    }

    private function tokenWasAlreadyRequested(ResearchRun $run, string $pageToken): bool
    {
        return $run->searchPages()
            ->where('request_page_token', $pageToken)
            ->exists();
    }

    private function shortSampleWarning(ResearchRun $run): ?string
    {
        return $run->collected_result_count < $run->requested_result_count
            ? 'YouTube returned fewer unique results than requested.'
            : null;
    }

    private function requestForRun(
        ResearchRun $run,
        int $maxResults,
        ?string $pageToken,
    ): VideoSearchRequest {
        return new VideoSearchRequest(
            query: $run->query_text,
            relevanceLanguage: $run->relevance_language,
            regionCode: $run->region_code,
            maxResults: $maxResults,
            pageToken: $pageToken,
            context: new ProviderRequestContext(
                userId: $run->user_id,
                researchRunId: $run->id,
            ),
            order: $this->stringParameter($run, 'search_order'),
            publishedAfter: $this->stringParameter($run, 'published_after'),
            publishedBefore: $this->stringParameter($run, 'published_before'),
            videoDuration: $this->stringParameter($run, 'video_duration'),
            videoCategoryId: $this->stringParameter($run, 'video_category_id'),
        );
    }

    private function stringParameter(ResearchRun $run, string $key): ?string
    {
        $value = $run->parameters[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
