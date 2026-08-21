<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $purpose
 * @property string|null $market_key
 * @property list<string>|null $themes
 * @property string $decision_status
 * @property string|null $decision_note
 * @property string|null $color
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'description', 'purpose', 'market_key', 'themes', 'decision_status', 'decision_note', 'color', 'archived_at'])]
class ResearchProject extends Model
{
    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ResearchQuery, $this> */
    public function queries(): HasMany
    {
        return $this->hasMany(ResearchQuery::class);
    }

    /** @return HasMany<DiscoveryRun, $this> */
    public function discoveryRuns(): HasMany
    {
        return $this->hasMany(DiscoveryRun::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<WatchlistItem, $this> */
    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    /** @return HasMany<TopicWorkspace, $this> */
    public function topicWorkspaces(): HasMany
    {
        return $this->hasMany(TopicWorkspace::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'immutable_datetime',
            'themes' => 'array',
        ];
    }
}
