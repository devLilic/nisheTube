<?php

namespace App\Domain\Scoring\Data;

final readonly class OpportunityScoreResult
{
    /**
     * @param  array<string, mixed>  $inputSummary
     * @param  array<string, string>  $explanations
     * @param  list<array{code: string, message: string}>  $warnings
     */
    public function __construct(
        public string $formulaVersion,
        public float $overallScore,
        public float $demandMomentumScore,
        public float $competitionOpportunityScore,
        public float $audienceReachabilityScore,
        public float $contentFreshnessGapScore,
        public float $creatorViabilityScore,
        public float $confidenceScore,
        public int $sampleSize,
        public array $inputSummary,
        public array $explanations,
        public array $warnings,
    ) {}

    /** @return array<string, mixed> */
    public function persistenceAttributes(): array
    {
        return [
            'formula_version' => $this->formulaVersion,
            'overall_score' => $this->decimal($this->overallScore),
            'demand_momentum_score' => $this->decimal($this->demandMomentumScore),
            'competition_opportunity_score' => $this->decimal($this->competitionOpportunityScore),
            'audience_reachability_score' => $this->decimal($this->audienceReachabilityScore),
            'content_freshness_gap_score' => $this->decimal($this->contentFreshnessGapScore),
            'creator_viability_score' => $this->decimal($this->creatorViabilityScore),
            'confidence_score' => $this->decimal($this->confidenceScore),
            'sample_size' => $this->sampleSize,
            'input_summary' => $this->inputSummary,
            'explanations' => $this->explanations,
            'warnings' => $this->warnings,
        ];
    }

    private function decimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
