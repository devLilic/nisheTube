<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use Generator;
use InvalidArgumentException;

class VideoSearchPaginator
{
    public function __construct(private readonly VideoResearchProvider $provider) {}

    /** @return Generator<int, VideoSearchPage> */
    public function pages(VideoSearchRequest $initialRequest, int $maxPages): Generator
    {
        if ($maxPages < 1 || $maxPages > 100) {
            throw new InvalidArgumentException('Search page count must be between 1 and 100.');
        }

        $request = $initialRequest;
        $seenTokens = [];

        for ($pageNumber = 1; $pageNumber <= $maxPages; $pageNumber++) {
            $page = $this->provider->search($request);
            yield $pageNumber => $page;

            if ($page->nextPageToken === null || $pageNumber === $maxPages) {
                return;
            }

            if (isset($seenTokens[$page->nextPageToken])) {
                throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
            }

            $seenTokens[$page->nextPageToken] = true;
            $request = $request->withPageToken($page->nextPageToken);
        }
    }
}
