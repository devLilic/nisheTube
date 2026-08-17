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
 * @property int $opportunity_score_id
 * @property string $formula_version
 * @property string $fit_score
 * @property string $confidence_score
 * @property array<string, mixed> $input_summary
 * @property array<string, string> $explanations
 * @property list<array{code: string, message: string}> $warnings
 * @property Carbon $calculated_at
 */
#[Fillable(['research_run_id', 'opportunity_score_id', 'formula_version', 'fit_score', 'confidence_score', 'input_summary', 'explanations', 'warnings', 'calculated_at'])]
class ProfitabilityFitScore extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('Calculated profitability-fit scores are immutable. Create a new formula version instead.');
        });
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return BelongsTo<OpportunityScore, $this> */
    public function opportunityScore(): BelongsTo
    {
        return $this->belongsTo(OpportunityScore::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fit_score' => 'decimal:4',
            'confidence_score' => 'decimal:4',
            'input_summary' => 'array',
            'explanations' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
