<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $kind
 * @property string $label
 * @property string $label_key
 * @property list<string> $evidence_video_ids
 */
#[Fillable(['semantic_topic_profile_id', 'kind', 'label', 'label_key', 'confidence', 'position', 'evidence_video_ids'])]
class SemanticClassification extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Semantic classifications are immutable.'));
    }

    /** @return BelongsTo<SemanticTopicProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SemanticTopicProfile::class, 'semantic_topic_profile_id');
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'position' => 'integer',
            'evidence_video_ids' => 'array',
        ];
    }
}
