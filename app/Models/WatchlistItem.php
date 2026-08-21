<?php

namespace App\Models;

use App\Domain\Watchlist\Enums\WatchlistStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $target_type
 * @property int $target_id
 * @property int|null $research_project_id
 * @property int|null $topic_workspace_id
 * @property WatchlistStatus $status
 * @property bool $is_active
 * @property string $refresh_mode
 * @property bool $notify_on_refresh
 * @property string|null $note
 * @property Carbon|null $last_observed_at
 * @property Carbon|null $last_refreshed_at
 * @property Carbon|null $next_refresh_at
 * @property int|null $last_refresh_run_id
 * @property Model|null $target
 * @property ResearchProject|null $project
 * @property TopicWorkspace|null $workspace
 * @property WatchlistRefreshRun|null $lastRefreshRun
 */
#[Fillable([
    'user_id', 'target_type', 'target_id', 'research_project_id', 'topic_workspace_id', 'status', 'is_active',
    'refresh_mode', 'notify_on_refresh', 'note', 'last_observed_at', 'last_refreshed_at', 'next_refresh_at', 'last_refresh_run_id',
])]
class WatchlistItem extends Model
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

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<ResearchProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    /** @return BelongsTo<TopicWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TopicWorkspace::class, 'topic_workspace_id');
    }

    /** @return HasMany<WatchlistRefreshRun, $this> */
    public function refreshRuns(): HasMany
    {
        return $this->hasMany(WatchlistRefreshRun::class);
    }

    /** @return BelongsTo<WatchlistRefreshRun, $this> */
    public function lastRefreshRun(): BelongsTo
    {
        return $this->belongsTo(WatchlistRefreshRun::class, 'last_refresh_run_id');
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    protected function casts(): array
    {
        return [
            'status' => WatchlistStatus::class,
            'is_active' => 'boolean',
            'notify_on_refresh' => 'boolean',
            'last_observed_at' => 'immutable_datetime',
            'last_refreshed_at' => 'immutable_datetime',
            'next_refresh_at' => 'immutable_datetime',
        ];
    }
}
