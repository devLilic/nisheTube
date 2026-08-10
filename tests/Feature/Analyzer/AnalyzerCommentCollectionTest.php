<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\YouTube\Contracts\CommentProvider;
use App\Domain\YouTube\Data\CommentThreadsPage;
use App\Domain\YouTube\Data\CommentThreadsRequest;
use App\Domain\YouTube\Data\PublicComment as CommentData;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Jobs\Comments\CollectPublicComments;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\CommentCollectionRun;
use App\Models\PublicComment;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerCommentCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_explicitly_queues_paginated_idempotent_collection_and_ui_exposes_honest_scope(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);

        $this->actingAs($owner)->post(route('analyzer.runs.comments.store', $run))->assertRedirect();
        $collection = CommentCollectionRun::query()->sole();
        Queue::assertPushed(CollectPublicComments::class, fn ($job): bool => $job->commentCollectionRunId === $collection->id);

        $provider = new PaginatedCommentProvider;
        $this->app->instance(CommentProvider::class, $provider);
        $this->app->call([new CollectPublicComments($collection->id), 'handle']);
        $this->app->call([new CollectPublicComments($collection->id), 'handle']);

        $collection->refresh();
        $this->assertSame('completed', $collection->status->value);
        $this->assertSame(2, $collection->pages_collected);
        $this->assertSame(2, $collection->comments_collected);
        $this->assertDatabaseCount('public_comments', 2);
        $this->assertDatabaseMissing('public_comments', ['text' => 'Author identity']);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.comments.status', 'completed')
                ->where('run.comments.reply_scope', 'top_level_only')
                ->where('run.comments.items.0.like_count', 5)
                ->where('run.comments.items.0.reply_count', 3)
                ->has('run.comments.retention_cutoff_at'));
    }

    public function test_guest_foreign_user_channel_and_active_duplicate_requests_are_rejected_or_isolated(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);

        $this->post(route('analyzer.runs.comments.store', $run))->assertRedirect(route('login'));
        $this->actingAs($other)->post(route('analyzer.runs.comments.store', $run))->assertForbidden();
        $this->actingAs($owner)->post(route('analyzer.runs.comments.store', $run))->assertRedirect();
        $this->actingAs($owner)->post(route('analyzer.runs.comments.store', $run))->assertRedirect();
        $this->assertDatabaseCount('comment_collection_runs', 1);

        DB::table('analyzer_runs')->where('id', $run->id)->update(['target_kind' => 'channel']);
        $this->actingAs($owner)->post(route('analyzer.runs.comments.store', $run->fresh()))->assertForbidden();
    }

    public function test_stored_comments_are_served_in_bounded_ten_item_pages_without_provider_work(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'video_id' => $run->video_id,
            'provider' => 'youtube',
            'provider_video_id' => $run->target_provider_id,
            'status' => 'completed',
            'max_comments' => 200,
            'page_size' => 100,
            'pages_collected' => 1,
            'comments_collected' => 25,
            'reply_scope' => 'top_level_only',
            'collected_at' => now(),
        ]);

        foreach (range(1, 25) as $index) {
            PublicComment::query()->create([
                'comment_collection_run_id' => $collection->id,
                'provider_comment_id' => "comment-{$index}",
                'text' => "Stored comment {$index}",
                'like_count' => 26 - $index,
                'reply_count' => 0,
                'published_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($owner)->get(route('analyzer.runs.show', [
            'analyzerRun' => $run,
            'comments_page' => 2,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->has('run.comments.items', 10)
            ->where('run.comments.items.0.text', 'Stored comment 11')
            ->where('run.comments.pagination.current_page', 2)
            ->where('run.comments.pagination.per_page', 10)
            ->where('run.comments.pagination.total', 25)
            ->where('run.comments.pagination.last_page', 3)
            ->where('run.comments.pagination.from', 11)
            ->where('run.comments.pagination.to', 20));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_empty_disabled_partial_quota_and_failed_provider_outcomes_remain_distinct(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);

        foreach ([
            'empty' => 'empty',
            'disabled' => 'comments_disabled',
            'quota' => 'quota_exhausted',
            'failed' => 'failed',
        ] as $mode => $expectedStatus) {
            $collection = $this->queueCollection($owner, $run);
            $this->app->instance(CommentProvider::class, new StateCommentProvider($mode));
            $this->app->call([new CollectPublicComments($collection->id), 'handle']);
            $this->assertSame($expectedStatus, $collection->fresh()->status->value);
        }

        config()->set('comments.max_comments', 1);
        $partial = $this->queueCollection($owner, $run);
        $this->app->instance(CommentProvider::class, new StateCommentProvider('partial'));
        $this->app->call([new CollectPublicComments($partial->id), 'handle']);
        $partial->refresh();
        $this->assertSame('partial', $partial->status->value);
        $this->assertSame(1, $partial->comments_collected);
        $this->assertNotNull($partial->next_page_token);
    }

    private function queueCollection(User $owner, AnalyzerRun $run): CommentCollectionRun
    {
        $this->actingAs($owner)->post(route('analyzer.runs.comments.store', $run))->assertRedirect();

        return CommentCollectionRun::query()->latest('id')->firstOrFail();
    }

    private function completedVideoRun(User $user): AnalyzerRun
    {
        $run = app(CreateAnalyzerRun::class)->handle($user, 'dQw4w9WgXcQ', CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create(['provider' => 'youtube', 'provider_channel_id' => 'channel-1', 'title' => 'Channel']);
        $video = Video::query()->create([
            'provider' => 'youtube', 'provider_video_id' => 'dQw4w9WgXcQ', 'channel_id' => $channel->id,
            'title' => 'Video', 'published_at' => now()->subDay(),
        ]);
        $snapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id, 'collection_run_id' => $run->collection_run_id,
            'view_count' => 100, 'like_count' => 10, 'comment_count' => 5, 'collected_at' => now(),
        ]);
        AnalyzerRunVideo::query()->create([
            'analyzer_run_id' => $run->id, 'video_id' => $video->id, 'video_snapshot_id' => $snapshot->id,
            'role' => 'anchor', 'source_position' => 1,
        ]);
        DB::table('analyzer_runs')->where('id', $run->id)->update([
            'video_id' => $video->id, 'channel_id' => $channel->id, 'status' => AnalyzerRunStatus::Completed->value,
            'progress_percent' => 100, 'completed_at' => now(), 'calculated_at' => now(),
        ]);

        return $run->fresh();
    }
}

final class PaginatedCommentProvider implements CommentProvider
{
    public function listCommentThreads(CommentThreadsRequest $request): CommentThreadsPage
    {
        $comment = $request->pageToken === null
            ? new CommentData('one', 'First question?', 5, 3, new DateTimeImmutable('2026-08-01T00:00:00Z'), null)
            : new CommentData('two', 'Second comment', 1, 0, new DateTimeImmutable('2026-08-02T00:00:00Z'), null);

        return new CommentThreadsPage([$comment], $request->pageToken === null ? 'page-2' : null, 2);
    }
}

final class StateCommentProvider implements CommentProvider
{
    public function __construct(private readonly string $mode) {}

    public function listCommentThreads(CommentThreadsRequest $request): CommentThreadsPage
    {
        return match ($this->mode) {
            'empty' => new CommentThreadsPage([]),
            'partial' => new CommentThreadsPage([
                new CommentData('bounded', 'Bounded sample', 1, 0, new DateTimeImmutable('2026-08-01T00:00:00Z'), null),
            ], 'more-comments', 20),
            'disabled' => throw new YouTubeProviderException(YouTubeErrorCode::CommentsDisabled),
            'quota' => throw new YouTubeProviderException(YouTubeErrorCode::QuotaExhausted),
            default => throw new YouTubeProviderException(YouTubeErrorCode::RequestInvalid),
        };
    }
}
