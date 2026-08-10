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
 * @property int|null $research_project_id
 * @property int $market_id
 * @property string $name
 * @property string $name_key
 * @property string|null $description
 * @property string $market_key
 * @property string|null $region_code
 * @property string $relevance_language
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ResearchProject|null $project
 * @property int $items_count
 */
#[Fillable(['user_id', 'research_project_id', 'market_id', 'name', 'name_key', 'description', 'market_key', 'region_code', 'relevance_language', 'archived_at'])]
class TopicWorkspace extends Model
{
    use HasPublicId;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ResearchProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    /** @return BelongsTo<Market, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /** @return HasMany<TopicWorkspaceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TopicWorkspaceItem::class);
    }

    /** @return HasMany<TopicWorkspaceLaunch, $this> */
    public function launches(): HasMany
    {
        return $this->hasMany(TopicWorkspaceLaunch::class);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
