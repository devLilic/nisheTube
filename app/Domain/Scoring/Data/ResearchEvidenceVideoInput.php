<?php

namespace App\Domain\Scoring\Data;

final readonly class ResearchEvidenceVideoInput
{
    /**
     * @param  list<string>  $semanticLabels
     * @param  list<string>  $topicLabels
     */
    public function __construct(
        public int $videoId,
        public int $channelId,
        public ?int $videoSnapshotId,
        public ?int $channelSnapshotId,
        public string $providerVideoId,
        public string $title,
        public ?string $categoryName,
        public array $semanticLabels,
        public array $topicLabels,
        public ?bool $isShort,
        public ?float $viewsPerDay,
        public ?int $viewCount,
        public int $resultRank,
    ) {}
}
