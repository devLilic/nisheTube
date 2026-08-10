<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transcript_document_id
 * @property int $position
 * @property int|null $start_ms
 * @property int|null $end_ms
 * @property string $text
 */
#[Fillable(['transcript_document_id', 'position', 'start_ms', 'end_ms', 'text'])]
class TranscriptSegment extends Model
{
    /** @return BelongsTo<TranscriptDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(TranscriptDocument::class, 'transcript_document_id');
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }
}
