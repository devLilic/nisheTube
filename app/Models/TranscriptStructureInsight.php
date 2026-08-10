<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transcript_structure_profile_id
 * @property string $kind
 * @property string $label
 * @property string|null $detail
 * @property string $confidence
 * @property int $position
 * @property int $start_offset
 * @property int $end_offset
 * @property int|null $start_ms
 * @property int|null $end_ms
 */
#[Fillable([
    'transcript_structure_profile_id', 'kind', 'label', 'detail', 'confidence', 'position',
    'start_offset', 'end_offset', 'start_ms', 'end_ms',
])]
class TranscriptStructureInsight extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Transcript structure insights are immutable.'));
    }

    /** @return BelongsTo<TranscriptStructureProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(TranscriptStructureProfile::class, 'transcript_structure_profile_id');
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'position' => 'integer',
            'start_offset' => 'integer',
            'end_offset' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }
}
