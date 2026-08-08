<?php

namespace Tests\Unit\Domain\YouTube;

use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Services\VideoSearchPaginator;
use App\Domain\YouTube\Services\YouTubeIdBatcher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class YouTubeHelpersTest extends TestCase
{
    public function test_ids_are_deduplicated_and_split_within_provider_limits(): void
    {
        $batches = (new YouTubeIdBatcher)->batches(
            ['video-1', 'video-2', 'video-1', 'video-3'],
            2,
        );

        $this->assertSame([
            ['video-1', 'video-2'],
            ['video-3'],
        ], $batches);
    }

    public function test_paginator_follows_provider_tokens_without_using_approximate_totals(): void
    {
        $provider = new class implements VideoResearchProvider
        {
            /** @var list<string|null> */
            public array $tokens = [];

            public function search(VideoSearchRequest $request): VideoSearchPage
            {
                $this->tokens[] = $request->pageToken;

                return $request->pageToken === null
                    ? new VideoSearchPage([], 'second-page', 999999)
                    : new VideoSearchPage([], null, 999999);
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

        $pages = iterator_to_array(
            (new VideoSearchPaginator($provider))->pages(new VideoSearchRequest('camera', 'en'), 5),
        );

        $this->assertCount(2, $pages);
        $this->assertSame([null, 'second-page'], $provider->tokens);
    }
}
