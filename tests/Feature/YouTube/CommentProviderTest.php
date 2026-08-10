<?php

namespace Tests\Feature\YouTube;

use App\Domain\YouTube\Contracts\CommentProvider;
use App\Domain\YouTube\Data\CommentThreadsRequest;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CommentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('youtube.api_key', 'comment-test-secret');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
    }

    public function test_it_normalizes_plain_top_level_comments_without_author_identity_and_records_quota(): void
    {
        $user = User::factory()->create();
        Http::fake(['*commentThreads*' => Http::response([
            'nextPageToken' => 'next-comments',
            'pageInfo' => ['totalResults' => 12],
            'items' => [[
                'snippet' => [
                    'totalReplyCount' => 4,
                    'topLevelComment' => [
                        'id' => 'comment-1',
                        'snippet' => [
                            'textOriginal' => 'How did you make this?',
                            'authorDisplayName' => 'Must not persist',
                            'likeCount' => 7,
                            'publishedAt' => '2026-08-01T10:00:00Z',
                            'updatedAt' => '2026-08-02T10:00:00Z',
                        ],
                    ],
                ],
            ]],
        ])]);

        $page = app(CommentProvider::class)->listCommentThreads(new CommentThreadsRequest(
            'dQw4w9WgXcQ',
            100,
            'page-2',
            new ProviderRequestContext(userId: $user->id),
        ));

        $this->assertSame('comment-1', $page->comments[0]->commentId);
        $this->assertSame('How did you make this?', $page->comments[0]->text);
        $this->assertSame(7, $page->comments[0]->likeCount);
        $this->assertSame(4, $page->comments[0]->replyCount);
        $this->assertSame('next-comments', $page->nextPageToken);
        $this->assertSame(12, $page->reportedTotalResults);
        $this->assertStringNotContainsString('Must not persist', json_encode($page));

        Http::assertSent(fn (Request $request): bool => $request['part'] === 'snippet'
            && $request['videoId'] === 'dQw4w9WgXcQ'
            && $request['maxResults'] === 100
            && $request['pageToken'] === 'page-2'
            && $request['textFormat'] === 'plainText');
        $this->assertDatabaseHas('api_usage_events', [
            'user_id' => $user->id,
            'endpoint' => 'commentThreads.list',
            'estimated_cost' => 1,
        ]);
        $this->assertStringNotContainsString('comment-test-secret', app('db')->table('api_usage_events')->first()->safe_error_code ?? '');
    }

    public function test_comments_disabled_maps_to_a_distinct_safe_non_retryable_error(): void
    {
        Http::fake(['*commentThreads*' => Http::response([
            'error' => ['errors' => [['reason' => 'commentsDisabled']]],
        ], 403)]);

        try {
            app(CommentProvider::class)->listCommentThreads(new CommentThreadsRequest('dQw4w9WgXcQ'));
            $this->fail('Expected a provider exception.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::CommentsDisabled, $exception->providerCode);
            $this->assertFalse($exception->isRetryable());
            $this->assertStringNotContainsString('comment-test-secret', $exception->getMessage());
        }
    }
}
