<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $normalized_word
 * @property string $display_word
 * @property bool $is_active
 * @property Carbon $excluded_at
 * @property Carbon|null $restored_at
 */
#[Fillable([
    'user_id', 'normalized_word', 'display_word', 'is_active', 'excluded_at', 'restored_at',
])]
class AudienceSignalExclusion extends Model
{
    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'excluded_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }
}
