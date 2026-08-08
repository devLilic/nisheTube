<?php

namespace App\Domain\YouTube\Providers;

use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Contracts\YouTubeApiClient;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Services\YouTubeChannelDetailsNormalizer;
use App\Domain\YouTube\Services\YouTubeSearchNormalizer;
use App\Domain\YouTube\Services\YouTubeVideoDetailsNormalizer;

class YouTubeDataApiProvider implements VideoResearchProvider
{
    public function __construct(
        private readonly YouTubeApiClient $client,
        private readonly YouTubeSearchNormalizer $normalizer,
        private readonly YouTubeVideoDetailsNormalizer $videoDetailsNormalizer,
        private readonly YouTubeChannelDetailsNormalizer $channelDetailsNormalizer,
    ) {}

    public function search(VideoSearchRequest $request): VideoSearchPage
    {
        $parameters = [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $request->query,
            'relevanceLanguage' => $request->relevanceLanguage,
            'maxResults' => $request->maxResults,
        ];

        if ($request->regionCode !== null) {
            $parameters['regionCode'] = $request->regionCode;
        }

        if ($request->pageToken !== null) {
            $parameters['pageToken'] = $request->pageToken;
        }

        if ($request->order !== null) {
            $parameters['order'] = $request->order;
        }

        if ($request->publishedAfter !== null) {
            $parameters['publishedAfter'] = $request->publishedAfter;
        }

        if ($request->publishedBefore !== null) {
            $parameters['publishedBefore'] = $request->publishedBefore;
        }

        if ($request->videoDuration !== null && $request->videoDuration !== 'any') {
            $parameters['videoDuration'] = $request->videoDuration;
        }

        if ($request->videoCategoryId !== null) {
            $parameters['videoCategoryId'] = $request->videoCategoryId;
        }

        return $this->normalizer->normalize(
            $this->client->get('search.list', $parameters, $request->context),
        );
    }

    public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch
    {
        $payload = $this->client->get('videos.list', [
            'part' => 'snippet,contentDetails,statistics',
            'id' => implode(',', $request->ids),
        ], $request->context);

        return $this->videoDetailsNormalizer->normalize(
            $payload,
            $request->ids,
            now()->toDateTimeImmutable(),
        );
    }

    public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch
    {
        $payload = $this->client->get('channels.list', [
            'part' => 'snippet,contentDetails,statistics',
            'id' => implode(',', $request->ids),
            'maxResults' => count($request->ids),
        ], $request->context);

        return $this->channelDetailsNormalizer->normalize(
            $payload,
            $request->ids,
            now()->toDateTimeImmutable(),
        );
    }
}
