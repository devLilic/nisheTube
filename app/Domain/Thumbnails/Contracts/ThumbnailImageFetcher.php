<?php

namespace App\Domain\Thumbnails\Contracts;

use App\Domain\Thumbnails\Data\ThumbnailImagePayload;

interface ThumbnailImageFetcher
{
    public function fetch(string $url): ThumbnailImagePayload;
}
