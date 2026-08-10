<?php

namespace App\Jobs\Comments;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Audience\Actions\CalculateAudienceSignals;
use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Domain\YouTube\Contracts\CommentProvider;
use App\Domain\YouTube\Data\CommentThreadsRequest;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\CommentCollectionRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class CollectPublicComments implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $commentCollectionRunId) {}

    public function uniqueId(): string
    {
        return (string) $this->commentCollectionRunId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("comment-run:{$this->commentCollectionRunId}"))->releaseAfter(5)->expireAfter(180)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(CommentProvider $provider): void
    {
        $run = CommentCollectionRun::query()->with('analyzerRun')->find($this->commentCollectionRunId);
        if ($run === null || $run->status->isTerminal()) {
            return;
        }
        if ($run->analyzerRun->user_id !== $run->user_id || $run->analyzerRun->status !== AnalyzerRunStatus::Completed) {
            $this->finish($run, CommentCollectionStatus::Unavailable, YouTubeErrorCode::VideoNotFound);

            return;
        }

        $run->update(['status' => CommentCollectionStatus::Collecting, 'started_at' => $run->started_at ?? now()]);

        try {
            while ($run->comments()->count() < $run->max_comments) {
                $remaining = $run->max_comments - $run->comments()->count();
                $page = $provider->listCommentThreads(new CommentThreadsRequest(
                    $run->provider_video_id,
                    min($run->page_size, $remaining),
                    $run->next_page_token,
                    new ProviderRequestContext(userId: $run->user_id),
                ));

                foreach ($page->comments as $comment) {
                    $run->comments()->updateOrCreate(
                        ['provider_comment_id' => $comment->commentId],
                        [
                            'text' => $comment->text,
                            'like_count' => $comment->likeCount,
                            'reply_count' => $comment->replyCount,
                            'published_at' => $comment->publishedAt,
                            'provider_updated_at' => $comment->updatedAt,
                        ],
                    );
                }

                $count = $run->comments()->count();
                $run->update([
                    'next_page_token' => $page->nextPageToken,
                    'pages_collected' => $run->pages_collected + 1,
                    'comments_collected' => $count,
                    'reported_total_results' => $page->reportedTotalResults ?? $run->reported_total_results,
                    'collected_at' => now(),
                ]);
                $run->refresh();

                if ($page->nextPageToken === null) {
                    $this->finish($run, $count === 0 ? CommentCollectionStatus::Empty : CommentCollectionStatus::Completed);

                    return;
                }
            }

            $this->finish($run, CommentCollectionStatus::Partial, null, 'The configured collection limit was reached before every public thread was retrieved.');
        } catch (YouTubeProviderException $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }
            $count = $run->comments()->count();
            $status = $count > 0 ? CommentCollectionStatus::Partial : match ($exception->providerCode) {
                YouTubeErrorCode::CommentsDisabled => CommentCollectionStatus::CommentsDisabled,
                YouTubeErrorCode::QuotaExhausted => CommentCollectionStatus::QuotaExhausted,
                YouTubeErrorCode::VideoNotFound => CommentCollectionStatus::Unavailable,
                default => CommentCollectionStatus::Failed,
            };
            $this->finish($run, $status, $exception->providerCode);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = CommentCollectionRun::query()->find($this->commentCollectionRunId);
        if ($run === null || $run->status->isTerminal()) {
            return;
        }
        $this->finish(
            $run,
            $run->comments()->exists() ? CommentCollectionStatus::Partial : CommentCollectionStatus::Failed,
            YouTubeErrorCode::Unavailable,
        );
    }

    private function finish(CommentCollectionRun $run, CommentCollectionStatus $status, ?YouTubeErrorCode $error = null, ?string $message = null): void
    {
        $run->update([
            'status' => $status,
            'comments_collected' => $run->comments()->count(),
            'error_code' => $error?->value,
            'error_message' => $message ?? $error?->safeMessage(),
            'completed_at' => $status === CommentCollectionStatus::Failed ? null : now(),
            'failed_at' => $status === CommentCollectionStatus::Failed ? now() : null,
            'collected_at' => $run->collected_at ?? now(),
        ]);

        if ($run->comments()->exists() && in_array($status, [CommentCollectionStatus::Completed, CommentCollectionStatus::Partial], true)) {
            app(CalculateAudienceSignals::class)->handle($run->fresh());
        }
    }
}
