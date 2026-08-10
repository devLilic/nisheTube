<?php

namespace App\Domain\Thumbnails\Contracts;

use App\Domain\Thumbnails\Data\ThumbnailFeatures;
use App\Domain\Thumbnails\Data\ThumbnailImagePayload;

interface ThumbnailAnalysisProvider
{
    public function name(): string;

    public function version(): string;

    public function analyze(ThumbnailImagePayload $image): ThumbnailFeatures;
}
