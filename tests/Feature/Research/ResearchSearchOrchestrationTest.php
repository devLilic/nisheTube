<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\QueueResearchRun;
use App\Domain\Research\Actions\RetryResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Jobs\Research\EnrichResearchRun;
use App\Models\ApiUsageEvent;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResearchSearchOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        config()->set('youtube.api_key', 'orchestration-test-secret');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
    }

    public function test_owner_can_queue_a_draft_run_and_foreign_users_cannot_queue_it(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);

        [$owner, $run] = $this->newRun();
        $otherUser = User::factory()->create();
        $queued = app(QueueResearchRun::class)->handle($owner, $run);

        $this->assertSame(ResearchRunStatus::Queued, $queued->status);
        Queue::assertPushed(CollectResearchRunSearch::class, function (CollectResearchRunSearch $job) use ($run): bool {
            return $job->researchRunId === $run->id;
        });

        try {
            app(QueueResearchRun::class)->handle($otherUser, $run);
            $this->fail('A foreign user should not be able to queue the run.');
        } catch (AuthorizationException) {
            $this->assertSame(ResearchRunStatus::Queued, $run->fresh()->status);
        }
    }

    public function test_search_job_paginates_frozen_filters_persists_progress_and_hands_off_to_enrichment(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [$user, $run] = $this->newRun(75);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
        $requests = [];

        Http::fake(function (Request $request) use (&$requests) {
            $requests[] = $request->data();
            $pageToken = $request->data()['pageToken'] ?? null;

            return $pageToken === null
                ? Http::response([
                    'nextPageToken' => 'page-2',
                    'pageInfo' => ['totalResults' => 5000],
                    'items' => $this->searchItems(1, 50),
                ])
                : Http::response([
                    'items' => $this->searchItems(51, 25),
                ]);
        });

        $job = new CollectResearchRunSearch($run->id);
        app()->call([$job, 'handle']);

        $run->refresh();

        $this->assertSame(ResearchRunStatus::Searching, $run->status);
        $this->assertSame(75, $run->collected_result_count);
        $this->assertSame(50, $run->progress_percent);
        $this->assertCount(2, $run->searchPages);
        $this->assertCount(75, $run->searchResults);
        $this->assertSame(5000, $run->searchPages->first()->approximate_total_results);
        $this->assertSame(1, $run->searchResults()->orderBy('result_rank')->firstOrFail()->result_rank);
        $this->assertSame(75, $run->searchResults()->orderByDesc('result_rank')->firstOrFail()->result_rank);
        $this->assertCount(2, $requests);
        $this->assertSame(50, $requests[0]['maxResults']);
        $this->assertSame(25, $requests[1]['maxResults']);
        $this->assertSame('page-2', $requests[1]['pageToken']);
        $this->assertSame('viewCount', $requests[0]['order']);
        $this->assertSame('2026-07-01T00:00:00+00:00', $requests[0]['publishedAfter']);
        $this->assertSame('2026-07-31T23:59:59+00:00', $requests[0]['publishedBefore']);
        $this->assertSame('medium', $requests[0]['videoDuration']);
        $this->assertSame('26', $requests[0]['videoCategoryId']);
        $this->assertSame('RO', $requests[0]['regionCode']);
        $this->assertSame('ro', $requests[0]['relevanceLanguage']);
        $this->assertSame($user->id, ApiUsageEvent::query()->where('research_run_id', $run->id)->firstOrFail()->user_id);
        $this->assertDatabaseCount('api_usage_events', 2);
        Queue::assertPushed(EnrichResearchRun::class, function (EnrichResearchRun $job) use ($run): bool {
            return $job->researchRunId === $run->id;
        });

        app()->call([$job, 'handle']);

        $this->assertDatabaseCount('research_run_search_pages', 2);
        $this->assertDatabaseCount('research_run_search_results', 75);
        $this->assertDatabaseCount('api_usage_events', 2);
        Http::assertSentCount(2);
    }

    public function test_partial_provider_data_after_a_saved_page_hands_off_with_a_safe_warning(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun(75);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push([
                    'nextPageToken' => 'page-2',
                    'items' => $this->searchItems(1, 50),
                ])
                ->push('not-json', 200),
        ]);

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        $run->refresh();

        $this->assertSame(50, $run->collected_result_count);
        $this->assertSame(50, $run->progress_percent);
        $this->assertContains(
            'YouTube returned incomplete data after partial collection; the saved results will continue to enrichment.',
            $run->collection_warnings,
        );
        $this->assertDatabaseCount('research_run_search_pages', 1);
        $this->assertDatabaseCount('research_run_search_results', 50);
        $this->assertDatabaseCount('api_usage_events', 2);
        Queue::assertPushed(EnrichResearchRun::class);
    }

    public function test_non_retryable_provider_failure_is_stored_safely_and_preserves_the_run(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun();
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'error' => [
                    'errors' => [['reason' => 'quotaExceeded']],
                    'message' => 'Unsafe orchestration-test-secret detail',
                ],
            ], 403),
        ]);

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        $run->refresh();

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->value, $run->error_code);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->safeMessage(), $run->error_message);
        $this->assertStringNotContainsString('orchestration-test-secret', $run->toJson());
        $this->assertNotNull($run->failed_at);
        $this->assertDatabaseCount('research_run_search_results', 0);
        $this->assertSame($run->id, ApiUsageEvent::query()->sole()->research_run_id);
        Queue::assertNotPushed(EnrichResearchRun::class);
    }

    public function test_transient_provider_failure_is_left_for_bounded_queue_retry_then_fails_safely(): void
    {
        [, $run] = $this->newRun();
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
        $job = new CollectResearchRunSearch($run->id);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'error' => ['errors' => [['reason' => 'backendError']]],
            ], 500),
        ]);

        try {
            app()->call([$job, 'handle']);
            $this->fail('A retryable provider failure should be returned to the queue.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::Unavailable, $exception->providerCode);
            $this->assertSame(ResearchRunStatus::Searching, $run->fresh()->status);
            $this->assertSame(3, $job->tries);
            $this->assertSame([5, 30], $job->backoff());

            $job->failed($exception);
        }

        $run->refresh();

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame(YouTubeErrorCode::Unavailable->value, $run->error_code);
        $this->assertSame(YouTubeErrorCode::Unavailable->safeMessage(), $run->error_message);
    }

    public function test_retry_creates_an_owned_queued_attempt_from_the_failed_runs_frozen_inputs(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);

        [$owner, $run] = $this->newRun(75);
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle(
            $run,
            ResearchRunStatus::Failed,
            new RunFailure('youtube_quota_exhausted', 'The YouTube API quota bucket is exhausted.'),
        );
        $run->researchQuery->update(['query_text' => 'Changed after the failed attempt']);

        $retry = app(RetryResearchRun::class)->handle($owner, $run);

        $this->assertSame(2, $retry->attempt_number);
        $this->assertSame(ResearchRunStatus::Queued, $retry->status);
        $this->assertSame($run->query_text, $retry->query_text);
        $this->assertSame($run->market_key, $retry->market_key);
        $this->assertSame($run->parameters, $retry->parameters);
        $this->assertSame($run->requested_result_count, $retry->requested_result_count);
        $this->assertNotSame($run->collection_run_id, $retry->collection_run_id);
        $this->assertSame($run->collectionRun->cache_policy, $retry->collectionRun->cache_policy);
        $this->assertSame($run->collectionRun->frozen_request, $retry->collectionRun->frozen_request);
        $this->assertSame(ResearchRunStatus::Failed, $run->fresh()->status);
        Queue::assertPushed(CollectResearchRunSearch::class, function (CollectResearchRunSearch $job) use ($retry): bool {
            return $job->researchRunId === $retry->id;
        });

        try {
            app(RetryResearchRun::class)->handle(User::factory()->create(), $run);
            $this->fail('A foreign user should not be able to retry the run.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('research_runs', 2);
        }
    }

    /** @return array{User, ResearchRun} */
    private function newRun(int $requestedResultCount = 75): array
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'ro_ro')->firstOrFail();
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: $market,
            queryText: 'camera research',
            searchOrder: SearchOrder::ViewCount,
            publishedAfter: CarbonImmutable::parse('2026-07-01 00:00:00 UTC'),
            publishedBefore: CarbonImmutable::parse('2026-07-31 23:59:59 UTC'),
            videoDuration: VideoDurationFilter::Medium,
            videoCategoryId: '26',
        );

        return [$user, app(CreateResearchRun::class)->handle($user, $query, $requestedResultCount)];
    }

    /** @return list<array<string, mixed>> */
    private function searchItems(int $start, int $count): array
    {
        $items = [];

        for ($number = $start; $number < $start + $count; $number++) {
            $items[] = [
                'id' => ['videoId' => "video-{$number}"],
                'snippet' => [
                    'channelId' => 'channel-'.(int) ceil($number / 3),
                    'title' => "Camera result {$number}",
                    'publishedAt' => '2026-08-01T12:00:00Z',
                ],
            ];
        }

        return $items;
    }
}
