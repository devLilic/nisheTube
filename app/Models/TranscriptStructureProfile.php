<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $analyzer_run_id
 * @property int $transcript_document_id
 * @property string $status
 * @property string $provenance
 * @property string $provider
 * @property string $algorithm_version
 * @property string $language
 * @property int $word_count
 * @property int $evidence_count
 * @property string|null $confidence_score
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'transcript_document_id', 'status', 'provenance', 'provider',
    'algorithm_version', 'language', 'word_count', 'evidence_count', 'confidence_score', 'warnings',
    'calculated_at',
])]
class TranscriptStructureProfile extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Transcript structure profiles are immutable.'));
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

    /** @return BelongsTo<TranscriptDocument, $this> */
    public function transcriptDocument(): BelongsTo
    {
        return $this->belongsTo(TranscriptDocument::class);
    }

    /** @return HasMany<TranscriptStructureInsight, $this> */
    public function insights(): HasMany
    {
        return $this->hasMany(TranscriptStructureInsight::class)->orderBy('kind')->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
            'evidence_count' => 'integer',
            'confidence_score' => 'decimal:4',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
