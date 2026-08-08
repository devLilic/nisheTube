<?php

namespace App\Domain\YouTube\Contracts;

use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;

interface VideoResearchProvider
{
    public function search(VideoSearchRequest $request): VideoSearchPage;

    public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch;

    public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch;
}
