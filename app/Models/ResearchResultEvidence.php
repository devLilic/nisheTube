<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $research_evidence_profile_id
 * @property int $research_run_id
 * @property int $video_id
 * @property int $channel_id
 * @property int|null $video_snapshot_id
 * @property int|null $channel_snapshot_id
 * @property string $relevance_class
 * @property string $relevance_score
 * @property string $format_class
 * @property array<string, mixed> $signals
 * @property array<string, mixed> $metric_inputs
 */
#[Fillable([
    'research_evidence_profile_id', 'research_run_id', 'video_id', 'channel_id',
    'video_snapshot_id', 'channel_snapshot_id', 'relevance_class', 'relevance_score',
    'format_class', 'signals', 'metric_inputs',
])]
class ResearchResultEvidence extends Model
{
    protected $table = 'research_result_evidence';

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Research result evidence is immutable.'));
        static::deleting(fn (): never => throw new DomainException('Research result evidence may only be removed with its profile.'));
    }

    /** @return BelongsTo<ResearchEvidenceProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ResearchEvidenceProfile::class, 'research_evidence_profile_id');
    }

    protected function casts(): array
    {
        return [
            'relevance_score' => 'decimal:4',
            'signals' => 'array',
            'metric_inputs' => 'array',
        ];
    }
}
