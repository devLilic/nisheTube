<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $analyzer_run_public_id
 * @property string $transcript_document_public_id
 * @property string $provider_video_id
 * @property string $provider_version
 * @property string $input_format
 * @property string $language
 * @property int $character_count
 * @property int $segment_count
 * @property Carbon $deleted_at
 */
#[Fillable([
    'user_id', 'analyzer_run_public_id', 'transcript_document_public_id', 'provider_video_id',
    'provider_version', 'input_format', 'language', 'character_count', 'segment_count', 'deleted_at',
])]
class TranscriptDeletionAudit extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'character_count' => 'integer',
            'segment_count' => 'integer',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
