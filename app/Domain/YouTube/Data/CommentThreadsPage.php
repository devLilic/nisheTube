<?php

namespace App\Domain\YouTube\Data;

final readonly class CommentThreadsPage
{
    /**
     * @param  list<PublicComment>  $comments
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $comments,
        public ?string $nextPageToken = null,
        public ?int $reportedTotalResults = null,
        public array $warnings = [],
    ) {}
}
