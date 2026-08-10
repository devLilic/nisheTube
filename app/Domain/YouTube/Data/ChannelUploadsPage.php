<?php

namespace App\Domain\YouTube\Data;

final readonly class ChannelUploadsPage
{
    /**
     * @param  list<ChannelUpload>  $uploads
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $uploads,
        public ?string $nextPageToken = null,
        public array $warnings = [],
    ) {}
}
