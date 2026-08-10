<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $audience_signal_profile_id
 * @property string $kind
 * @property string $label
 * @property string $label_key
 * @property string $confidence
 * @property int $comment_count
 * @property int $occurrence_count
 * @property int $position
 */
#[Fillable([
    'audience_signal_profile_id', 'kind', 'label', 'label_key', 'confidence', 'comment_count',
    'occurrence_count', 'position',
])]
class AudienceSignal extends Model
{
    /** @return BelongsTo<AudienceSignalProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AudienceSignalProfile::class, 'audience_signal_profile_id');
    }

    /** @return BelongsToMany<PublicComment, $this> */
    public function evidenceComments(): BelongsToMany
    {
        return $this->belongsToMany(PublicComment::class, 'audience_signal_evidence')->orderBy('public_comments.id');
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'comment_count' => 'integer',
            'occurrence_count' => 'integer',
            'position' => 'integer',
        ];
    }
}
