<?php

namespace App\Domain\Semantic\Data;

final readonly class SemanticProfileResult
{
    /**
     * @param  list<SemanticLabel>  $topics
     * @param  list<SemanticLabel>  $contentPillars
     * @param  list<string>  $warnings
     * @param  list<int>  $inputMembershipIds
     */
    public function __construct(
        public string $status,
        public string $language,
        public ?SemanticLabel $niche,
        public ?SemanticLabel $subniche,
        public array $topics,
        public array $contentPillars,
        public ?float $concentrationScore,
        public ?float $confidenceScore,
        public array $warnings,
        public array $inputMembershipIds = [],
    ) {}
}
