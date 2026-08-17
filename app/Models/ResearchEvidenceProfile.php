<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $research_run_id
 * @property string $evidence_version
 * @property string $normalization_version
 * @property int $full_sample_count
 * @property int $strict_sample_count
 * @property array<string, mixed> $input_summary
 * @property array<string, mixed> $sample_evidence
 * @property array<string, mixed> $format_evidence
 * @property array<string, mixed> $outlier_evidence
 * @property array<string, mixed> $stability_evidence
 * @property list<string>|null $warnings
 * @property CarbonImmutable $calculated_at
 */
#[Fillable([
    'research_run_id', 'evidence_version', 'normalization_version', 'full_sample_count',
    'strict_sample_count', 'input_summary', 'sample_evidence', 'format_evidence',
    'outlier_evidence', 'stability_evidence', 'warnings', 'calculated_at',
])]
class ResearchEvidenceProfile extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Research evidence profiles are immutable.'));
        static::deleting(fn (): never => throw new DomainException('Research evidence profiles may only be removed with their research run.'));
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return HasMany<ResearchResultEvidence, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ResearchResultEvidence::class);
    }

    protected function casts(): array
    {
        return [
            'full_sample_count' => 'integer',
            'strict_sample_count' => 'integer',
            'input_summary' => 'array',
            'sample_evidence' => 'array',
            'format_evidence' => 'array',
            'outlier_evidence' => 'array',
            'stability_evidence' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
