<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\QueueResearchRun;
use App\Domain\Research\Actions\RetryResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
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
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResearchSearchAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        config()->set('youtube.api_key', 'search-acceptance-secret');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
    }

    public function test_search_submission_rejects_each_invalid_boundary_without_creating_or_queueing_work(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);

        $user = User::factory()->create();
        Market::query()->where('key', 'ru_ru')->update(['is_enabled' => false]);
        $valid = [
            'query_text' => 'camera research',
            'market_key' => 'global_en',
            'requested_result_count' => 25,
            'search_order' => 'relevance',
            'published_window' => 'any',
            'video_duration' => 'any',
            'video_category_id' => null,
        ];
        $invalidCases = [
            'blank query' => [['query_text' => '   '], ['query_text']],
            'long query' => [['query_text' => str_repeat('x', 501)], ['query_text']],
            'disabled market' => [['market_key' => 'ru_ru'], ['market_key']],
            'unknown market' => [['market_key' => 'not_a_market'], ['market_key']],
            'unsupported depth' => [['requested_result_count' => 75], ['requested_result_count']],
            'unknown order' => [['search_order' => 'popular'], ['search_order']],
            'unknown window' => [['published_window' => 'lifetime'], ['published_window']],
            'missing custom dates' => [['published_window' => 'custom'], ['published_after', 'published_before']],
            'invalid custom date' => [[
                'published_window' => 'custom',
                'published_after' => '08/01/2026',
                'published_before' => '2026-08-08',
            ], ['published_after']],
            'reversed custom dates' => [[
                'published_window' => 'custom',
                'published_after' => '2026-08-08',
                'published_before' => '2026-08-01',
            ], ['published_before']],
            'unknown duration' => [['video_duration' => 'tiny'], ['video_duration']],
            'invalid category' => [['video_category_id' => '2 6'], ['video_category_id']],
        ];

        foreach ($invalidCases as $case) {
            [$overrides, $expectedErrors] = $case;

            $this->actingAs($user)
                ->from(route('research.create'))
                ->post(route('research.store'), array_replace($valid, $overrides))
                ->assertRedirect(route('research.create'))
                ->assertSessionHasErrors($expectedErrors);
        }

        $this->assertDatabaseCount('research_queries', 0);
        $this->assertDatabaseCount('research_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_collection_uses_the_runs_frozen_values_after_the_query_and_market_change(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun(25);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
        $run->researchQuery->update([
            'query_text' => 'changed source query',
            'search_order' => SearchOrder::Date,
            'published_after' => CarbonImmutable::parse('2026-08-01 00:00:00 UTC'),
            'published_before' => CarbonImmutable::parse('2026-08-02 00:00:00 UTC'),
            'video_duration' => VideoDurationFilter::Long,
            'video_category_id' => '10',
        ]);
        $run->researchQuery->market->update([
            'region_code' => 'MD',
            'relevance_language' => 'en',
        ]);
        $requestData = null;

        Http::fake(function (Request $request) use (&$requestData) {
            $requestData = $request->data();

            return Http::response(['items' => $this->searchItems(1, 25)]);
        });

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        $this->assertIsArray($requestData);
        $this->assertSame('camera research', $requestData['q']);
        $this->assertSame('viewCount', $requestData['order']);
        $this->assertSame('2026-07-01T00:00:00+00:00', $requestData['publishedAfter']);
        $this->assertSame('2026-07-31T23:59:59+00:00', $requestData['publishedBefore']);
        $this->assertSame('medium', $requestData['videoDuration']);
        $this->assertSame('26', $requestData['videoCategoryId']);
        $this->assertSame('RO', $requestData['regionCode']);
        $this->assertSame('ro', $requestData['relevanceLanguage']);
        $this->assertSame(25, $run->fresh()->collected_result_count);
        Queue::assertPushed(EnrichResearchRun::class, 1);
    }

    public function test_retryable_failure_resumes_from_the_saved_page_without_duplicate_data(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun();
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
        $job = new CollectResearchRunSearch($run->id);

        $providerCalls = 0;
        $resuming = false;
        $resumedRequest = null;
        Http::fake(function (Request $request) use (&$providerCalls, &$resuming, &$resumedRequest) {
            $providerCalls++;

            if ($resuming) {
                $resumedRequest = $request->data();

                return Http::response(['items' => $this->searchItems(51, 25)]);
            }

            return $providerCalls === 1
                ? Http::response([
                    'nextPageToken' => 'page-2',
                    'items' => $this->searchItems(1, 50),
                ])
                : Http::response([
                    'error' => ['errors' => [['reason' => 'backendError']]],
                ], 500);
        });

        try {
            app()->call([$job, 'handle']);
            $this->fail('A retryable provider failure should be returned to the queue.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::Unavailable, $exception->providerCode);
        }

        $this->assertSame(ResearchRunStatus::Searching, $run->fresh()->status);
        $this->assertDatabaseCount('research_run_search_pages', 1);
        $this->assertDatabaseCount('research_run_search_results', 50);
        $this->assertDatabaseCount('api_usage_events', 2);
        $this->assertSame(2, $providerCalls);

        $resuming = true;
        app()->call([$job, 'handle']);

        $this->assertIsArray($resumedRequest);
        $this->assertSame('page-2', $resumedRequest['pageToken']);
        $this->assertSame(25, $resumedRequest['maxResults']);
        $this->assertSame(75, $run->fresh()->collected_result_count);
        $this->assertDatabaseCount('research_run_search_pages', 2);
        $this->assertDatabaseCount('research_run_search_results', 75);
        $this->assertDatabaseCount('api_usage_events', 3);
        Queue::assertPushed(EnrichResearchRun::class, 1);
    }

    public function test_repeated_page_tokens_and_results_stop_collection_without_duplicate_rows_or_calls(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun(200);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push([
                    'nextPageToken' => 'repeated-token',
                    'items' => $this->searchItems(1, 2),
                ])
                ->push([
                    'nextPageToken' => 'repeated-token',
                    'items' => $this->searchItems(2, 2),
                ]),
        ]);

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        $run->refresh();

        $this->assertSame(3, $run->collected_result_count);
        $this->assertSame(50, $run->progress_percent);
        $this->assertContains(
            'YouTube returned a repeated page token, so collection stopped with the results already saved.',
            $run->collection_warnings,
        );
        $this->assertDatabaseCount('research_run_search_pages', 2);
        $this->assertDatabaseCount('research_run_search_results', 3);
        $this->assertDatabaseCount('api_usage_events', 2);
        Http::assertSentCount(2);
        Queue::assertPushed(EnrichResearchRun::class, 1);
    }

    public function test_quota_failure_after_a_saved_page_preserves_partial_results_and_safe_context(): void
    {
        Queue::fake([EnrichResearchRun::class]);

        [, $run] = $this->newRun();
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push([
                    'nextPageToken' => 'page-2',
                    'items' => $this->searchItems(1, 50),
                ])
                ->push([
                    'error' => [
                        'errors' => [['reason' => 'quotaExceeded']],
                        'message' => 'Unsafe search-acceptance-secret detail',
                    ],
                ], 403),
        ]);

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        $run->refresh();

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->value, $run->error_code);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->safeMessage(), $run->error_message);
        $this->assertSame(50, $run->collected_result_count);
        $this->assertStringNotContainsString('search-acceptance-secret', $run->toJson());
        $this->assertDatabaseCount('research_run_search_pages', 1);
        $this->assertDatabaseCount('research_run_search_results', 50);
        $this->assertDatabaseCount('api_usage_events', 2);
        $this->assertSame($run->id, ApiUsageEvent::query()->latest('id')->firstOrFail()->research_run_id);
        Queue::assertNotPushed(EnrichResearchRun::class);
    }

    public function test_queue_and_retry_actions_reject_runs_in_the_wrong_state(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);

        [$owner, $run] = $this->newRun(25);
        $run = app(QueueResearchRun::class)->handle($owner, $run);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Searching);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Enriching);

        try {
            app(QueueResearchRun::class)->handle($owner, $run);
            $this->fail('An enriching run should not be queued for search again.');
        } catch (DomainException $exception) {
            $this->assertSame('Only draft, queued, or interrupted searching runs may be queued.', $exception->getMessage());
        }

        try {
            app(RetryResearchRun::class)->handle($owner, $run);
            $this->fail('A non-failed run should not create a retry attempt.');
        } catch (DomainException $exception) {
            $this->assertSame('Only failed research runs may be retried.', $exception->getMessage());
        }

        $this->assertDatabaseCount('research_runs', 1);
        Queue::assertPushed(CollectResearchRunSearch::class, 1);
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
