<?php

namespace App\Models;

use App\Domain\Discovery\Enums\CandidateEvidenceState;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\Concerns\HasLibraryEntries;
use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $discovery_run_id
 * @property string $phrase
 * @property string $phrase_key
 * @property string $cluster_key
 * @property string|null $summary
 * @property array<string, mixed> $evidence
 * @property float $overall_score
 * @property float $confidence_score
 * @property string $formula_version
 * @property CandidateEvidenceState $evidence_state
 * @property NicheCandidateStatus $status
 * @property int|null $validation_research_run_id
 * @property-read DiscoveryRun $discoveryRun
 * @property-read ResearchRun|null $validationResearchRun
 */
#[Fillable([
    'discovery_run_id',
    'phrase',
    'cluster_key',
    'summary',
    'evidence',
    'overall_score',
    'confidence_score',
    'formula_version',
    'evidence_state',
    'status',
    'validation_research_run_id',
])]
class NicheCandidate extends Model
{
    use HasLibraryEntries, HasPublicId;

    protected static function booted(): void
    {
        static::saving(function (self $candidate): void {
            $phrase = Str::squish($candidate->phrase);

            if ($phrase === '') {
                throw new DomainException('A niche candidate phrase cannot be empty.');
            }

            $candidate->phrase = $phrase;
            $candidate->phrase_key = Str::lower($phrase);
        });

        static::updating(function (self $candidate): void {
            $immutable = [
                'discovery_run_id', 'phrase', 'cluster_key', 'summary', 'evidence',
                'overall_score', 'confidence_score', 'formula_version', 'evidence_state',
            ];

            if ($candidate->isDirty($immutable)) {
                throw new DomainException('Candidate evidence is immutable; create a new discovery run for new evidence.');
            }
        });
    }

    /** @return BelongsTo<DiscoveryRun, $this> */
    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(DiscoveryRun::class);
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function validationResearchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class, 'validation_research_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'overall_score' => 'float',
            'confidence_score' => 'float',
            'status' => NicheCandidateStatus::class,
            'evidence_state' => CandidateEvidenceState::class,
        ];
    }
}
