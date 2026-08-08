<?php

namespace Tests\Feature\YouTube;

use App\Domain\Settings\Data\FrozenMarketParameters;
use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Domain\YouTube\Services\VideoSearchPaginator;
use App\Models\ApiUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YouTubeProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('youtube.api_key', 'test-secret-api-key');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
    }

    public function test_search_uses_the_laravel_adapter_normalizes_results_and_records_quota(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'nextPageToken' => 'next-page',
                'pageInfo' => ['totalResults' => 123],
                'items' => [
                    [
                        'id' => ['videoId' => 'video-1'],
                        'snippet' => [
                            'channelId' => 'channel-1',
                            'title' => 'Romanian camera guide',
                            'publishedAt' => '2026-08-01T12:00:00Z',
                        ],
                    ],
                    ['id' => ['videoId' => 'incomplete-video']],
                ],
            ]),
        ]);

        $market = new FrozenMarketParameters('ro_ro', 'RO', 'ro');
        $request = VideoSearchRequest::forMarket(
            query: 'camera',
            market: $market,
            maxResults: 25,
            context: new ProviderRequestContext(userId: $user->id),
        );

        $page = app(VideoResearchProvider::class)->search($request);

        $this->assertCount(1, $page->results);
        $this->assertSame('video-1', $page->results[0]->videoId);
        $this->assertSame('next-page', $page->nextPageToken);
        $this->assertSame(123, $page->approximateTotalResults);
        $this->assertTrue($page->isPartial());

        Http::assertSent(function (Request $sent): bool {
            return $sent['key'] === 'test-secret-api-key'
                && $sent['q'] === 'camera'
                && $sent['type'] === 'video'
                && $sent['regionCode'] === 'RO'
                && $sent['relevanceLanguage'] === 'ro'
                && $sent['maxResults'] === 25;
        });

        $event = ApiUsageEvent::query()->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('search', $event->quota_bucket);
        $this->assertSame('search.list', $event->endpoint);
        $this->assertSame(1, $event->estimated_cost);
        $this->assertSame(QuotaUsageOutcome::Succeeded, $event->outcome);
        $this->assertNull($event->safe_error_code);
        $this->assertStringNotContainsString('test-secret-api-key', $event->toJson());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('youtubeQuota.label', 'NisheTube estimate')
                ->where('youtubeQuota.authoritative', false)
                ->where('youtubeQuota.buckets.0.bucket', 'search')
                ->where('youtubeQuota.buckets.0.used', 1)
                ->where('youtubeQuota.buckets.0.remaining', 99)
                ->where('youtubeQuota.buckets.0.last_endpoint', 'search.list'));
    }

    #[DataProvider('marketRequestMappings')]
    public function test_search_maps_each_market_to_exact_provider_parameters(
        FrozenMarketParameters $market,
        ?string $expectedRegion,
        string $expectedLanguage,
    ): void {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response(['items' => []]),
        ]);

        app(VideoResearchProvider::class)->search(VideoSearchRequest::forMarket(
            query: 'market research',
            market: $market,
        ));

        Http::assertSent(function (Request $sent) use ($expectedRegion, $expectedLanguage): bool {
            $hasExpectedRegion = $expectedRegion === null
                ? ! isset($sent['regionCode'])
                : $sent['regionCode'] === $expectedRegion;

            return $hasExpectedRegion
                && $sent['relevanceLanguage'] === $expectedLanguage
                && $sent['type'] === 'video';
        });
    }

    public function test_http_pagination_follows_next_page_tokens_and_records_each_call(): void
    {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push(['items' => [], 'nextPageToken' => 'page-2'])
                ->push(['items' => []]),
        ]);

        $provider = app(VideoResearchProvider::class);
        $pages = iterator_to_array((new VideoSearchPaginator($provider))->pages(
            new VideoSearchRequest('camera', 'en'),
            5,
        ));

        $pageTokens = [];
        Http::assertSent(function (Request $sent) use (&$pageTokens): bool {
            $pageTokens[] = $sent->data()['pageToken'] ?? null;

            return true;
        });

        $this->assertCount(2, $pages);
        $this->assertSame([null, 'page-2'], $pageTokens);
        $this->assertDatabaseCount('api_usage_events', 2);
    }

    public function test_missing_key_fails_safely_without_sending_or_recording_a_request(): void
    {
        config()->set('youtube.api_key', null);

        try {
            app(VideoResearchProvider::class)->search(new VideoSearchRequest('camera', 'en'));
            $this->fail('Expected the provider to reject a missing API key.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::KeyMissing, $exception->providerCode);
            $this->assertStringNotContainsString('test-secret-api-key', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_quota_exhaustion_is_normalized_recorded_and_reflected_in_summary(): void
    {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'error' => [
                    'errors' => [['reason' => 'quotaExceeded']],
                    'message' => 'Unsafe upstream detail test-secret-api-key',
                ],
            ], 403),
        ]);

        try {
            app(VideoResearchProvider::class)->search(new VideoSearchRequest('camera', 'en'));
            $this->fail('Expected quota exhaustion.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::QuotaExhausted, $exception->providerCode);
            $this->assertStringNotContainsString('test-secret-api-key', $exception->getMessage());
            $this->assertFalse($exception->isRetryable());
        }

        $event = ApiUsageEvent::query()->sole();
        $this->assertSame(QuotaUsageOutcome::Failed, $event->outcome);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->value, $event->safe_error_code);

        $summary = app(QuotaLedger::class)->summary();
        $this->assertTrue($summary->buckets[0]->exhausted);
    }

    public function test_invalid_key_is_normalized_without_leaking_upstream_details(): void
    {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'error' => [
                    'errors' => [['reason' => 'keyInvalid']],
                    'message' => 'Rejected credential test-secret-api-key',
                ],
            ], 400),
        ]);

        try {
            app(VideoResearchProvider::class)->search(new VideoSearchRequest('camera', 'en'));
            $this->fail('Expected the provider to reject the invalid API key.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::KeyInvalid, $exception->providerCode);
            $this->assertSame('The configured YouTube API key was rejected.', $exception->getMessage());
            $this->assertStringNotContainsString('test-secret-api-key', $exception->getMessage());
        }

        $event = ApiUsageEvent::query()->sole();
        $this->assertSame(YouTubeErrorCode::KeyInvalid->value, $event->safe_error_code);
        $this->assertStringNotContainsString('test-secret-api-key', $event->toJson());
    }

    public function test_malformed_success_payload_fails_safely_and_records_the_attempt(): void
    {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response('null', 200),
        ]);

        try {
            app(VideoResearchProvider::class)->search(new VideoSearchRequest('camera', 'en'));
            $this->fail('Expected a malformed payload failure.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::PartialData, $exception->providerCode);
            $this->assertStringNotContainsString('test-secret-api-key', $exception->getMessage());
        }

        $event = ApiUsageEvent::query()->sole();
        $this->assertSame(QuotaUsageOutcome::Failed, $event->outcome);
        $this->assertSame(YouTubeErrorCode::PartialData->value, $event->safe_error_code);
    }

    public function test_transient_failure_retries_and_charges_each_physical_attempt(): void
    {
        config()->set('youtube.max_attempts', 2);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push(['error' => ['errors' => [['reason' => 'backendError']]]], 500)
                ->push(['items' => []], 200),
        ]);

        $page = app(VideoResearchProvider::class)->search(new VideoSearchRequest('camera', 'en'));

        $this->assertSame([], $page->results);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('api_usage_events', 2);
        $this->assertSame(2, ApiUsageEvent::query()->sum('estimated_cost'));
        $this->assertSame(
            [QuotaUsageOutcome::Failed, QuotaUsageOutcome::Succeeded],
            ApiUsageEvent::query()->orderBy('id')->pluck('outcome')->all(),
        );
    }

    /**
     * @return iterable<string, array{FrozenMarketParameters, string|null, string}>
     */
    public static function marketRequestMappings(): iterable
    {
        yield 'global English omits region' => [
            new FrozenMarketParameters('global_en', null, 'en'),
            null,
            'en',
        ];
        yield 'Romania Romanian' => [
            new FrozenMarketParameters('ro_ro', 'RO', 'ro'),
            'RO',
            'ro',
        ];
        yield 'Russia Russian' => [
            new FrozenMarketParameters('ru_ru', 'RU', 'ru'),
            'RU',
            'ru',
        ];
    }
}
