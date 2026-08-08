<?php

namespace App\Models;

use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
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
 * @property int|null $initiated_by_user_id
 * @property CleanupMode $mode
 * @property CleanupStatus $status
 * @property Carbon $cutoff_at
 * @property bool $dry_run
 * @property array<string, int> $eligible_counts
 * @property array<string, int> $deleted_counts
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $error_code
 * @property string|null $error_message
 */
#[Fillable([
    'user_id',
    'initiated_by_user_id',
    'mode',
    'status',
    'cutoff_at',
    'dry_run',
    'eligible_counts',
    'deleted_counts',
    'started_at',
    'completed_at',
    'failed_at',
    'error_code',
    'error_message',
])]
class CleanupRun extends Model
{
    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** @return HasMany<SnapshotDeletionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SnapshotDeletionItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => CleanupMode::class,
            'status' => CleanupStatus::class,
            'cutoff_at' => 'immutable_datetime',
            'dry_run' => 'boolean',
            'eligible_counts' => 'array',
            'deleted_counts' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
