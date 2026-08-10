<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $analyzer_run_id
 * @property string $status
 * @property string $provenance
 * @property string $provider
 * @property string $algorithm_version
 * @property string $language
 * @property string|null $niche_label
 * @property string|null $niche_key
 * @property string|null $niche_confidence
 * @property string|null $subniche_label
 * @property string|null $subniche_key
 * @property string|null $subniche_confidence
 * @property string|null $concentration_score
 * @property string|null $confidence_score
 * @property array<string, mixed> $evidence_summary
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'status', 'provenance', 'provider', 'algorithm_version', 'language',
    'niche_label', 'niche_key', 'niche_confidence', 'subniche_label', 'subniche_key', 'subniche_confidence',
    'concentration_score', 'confidence_score', 'evidence_summary', 'warnings', 'calculated_at',
])]
class SemanticTopicProfile extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Semantic topic profiles are immutable.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    /** @return HasMany<SemanticClassification, $this> */
    public function classifications(): HasMany
    {
        return $this->hasMany(SemanticClassification::class)->orderBy('kind')->orderBy('position');
    }

    /** @return HasOne<SemanticPerformanceProfile, $this> */
    public function performanceProfile(): HasOne
    {
        return $this->hasOne(SemanticPerformanceProfile::class);
    }

    protected function casts(): array
    {
        return [
            'niche_confidence' => 'decimal:4',
            'subniche_confidence' => 'decimal:4',
            'concentration_score' => 'decimal:4',
            'confidence_score' => 'decimal:4',
            'evidence_summary' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
