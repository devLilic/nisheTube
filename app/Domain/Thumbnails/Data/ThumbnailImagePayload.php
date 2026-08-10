<?php

namespace App\Domain\Thumbnails\Data;

final readonly class ThumbnailImagePayload
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public string $checksum,
    ) {}
}
