<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $research_run_id
 * @property string $formula_version
 * @property string $overall_score
 * @property string $demand_momentum_score
 * @property string $competition_opportunity_score
 * @property string $audience_reachability_score
 * @property string $content_freshness_gap_score
 * @property string $creator_viability_score
 * @property string $confidence_score
 * @property int $sample_size
 * @property array<string, mixed> $input_summary
 * @property array<string, string> $explanations
 * @property list<array{code: string, message: string}> $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'research_run_id',
    'formula_version',
    'overall_score',
    'demand_momentum_score',
    'competition_opportunity_score',
    'audience_reachability_score',
    'content_freshness_gap_score',
    'creator_viability_score',
    'confidence_score',
    'sample_size',
    'input_summary',
    'explanations',
    'warnings',
    'calculated_at',
])]
class OpportunityScore extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('Calculated opportunity scores are immutable. Create a new formula version instead.');
        });
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:4',
            'demand_momentum_score' => 'decimal:4',
            'competition_opportunity_score' => 'decimal:4',
            'audience_reachability_score' => 'decimal:4',
            'content_freshness_gap_score' => 'decimal:4',
            'creator_viability_score' => 'decimal:4',
            'confidence_score' => 'decimal:4',
            'sample_size' => 'integer',
            'input_summary' => 'array',
            'explanations' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
