<?php

namespace Tests\Unit\Domain\YouTube;

use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\VideoSearchResult;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoResearchProviderContractTest extends TestCase
{
    public function test_search_boundary_normalizes_request_values_and_returns_typed_results(): void
    {
        $provider = new class implements VideoResearchProvider
        {
            public ?VideoSearchRequest $received = null;

            public function search(VideoSearchRequest $request): VideoSearchPage
            {
                $this->received = $request;

                return new VideoSearchPage(
                    results: [
                        new VideoSearchResult(
                            videoId: 'video-1',
                            channelId: 'channel-1',
                            title: 'A useful result',
                            publishedAt: new DateTimeImmutable('2026-08-01T12:00:00+00:00'),
                        ),
                    ],
                    nextPageToken: 'next-page',
                    approximateTotalResults: 120,
                );
            }

            public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch
            {
                return new VideoDetailsBatch([], new DateTimeImmutable);
            }

            public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch
            {
                return new ChannelDetailsBatch([], new DateTimeImmutable);
            }
        };

        $page = $provider->search(new VideoSearchRequest(
            query: '  camera reviews  ',
            relevanceLanguage: 'EN',
            regionCode: 'ro',
            maxResults: 25,
            pageToken: ' page-2 ',
        ));

        $this->assertSame('camera reviews', $provider->received->query);
        $this->assertSame('en', $provider->received->relevanceLanguage);
        $this->assertSame('RO', $provider->received->regionCode);
        $this->assertSame('page-2', $provider->received->pageToken);
        $this->assertSame('video-1', $page->results[0]->videoId);
        $this->assertSame('next-page', $page->nextPageToken);
        $this->assertSame(120, $page->approximateTotalResults);
    }

    /** @param array<string, mixed> $arguments */
    #[DataProvider('invalidRequests')]
    public function test_invalid_provider_requests_are_rejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VideoSearchRequest(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'blank query' => [['query' => '', 'relevanceLanguage' => 'en']];
        yield 'invalid language' => [['query' => 'test', 'relevanceLanguage' => 'english']];
        yield 'invalid region' => [['query' => 'test', 'relevanceLanguage' => 'en', 'regionCode' => 'ROM']];
        yield 'page too small' => [['query' => 'test', 'relevanceLanguage' => 'en', 'maxResults' => 0]];
        yield 'page too large' => [['query' => 'test', 'relevanceLanguage' => 'en', 'maxResults' => 51]];
    }
}
