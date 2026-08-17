<?php

namespace App\Domain\Discovery\Data;

use App\Domain\Discovery\Enums\CandidateEvidenceState;

final readonly class DiscoveryCandidateDraft
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $phrase,
        public string $clusterKey,
        public string $summary,
        public array $evidence,
        public float $overallScore,
        public float $confidenceScore,
        public string $formulaVersion,
        public CandidateEvidenceState $evidenceState = CandidateEvidenceState::Legacy,
    ) {}
}
