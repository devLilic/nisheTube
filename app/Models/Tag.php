<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $name
 * @property string $name_key
 * @property string|null $color
 */
#[Fillable(['user_id', 'name', 'name_key', 'color'])]
class Tag extends Model
{
    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Taggable, $this> */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }
}
