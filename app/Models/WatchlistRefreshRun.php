<?php

namespace App\Models;

use App\Domain\Watchlist\Enums\WatchlistRefreshStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $watchlist_item_id
 * @property int $user_id
 * @property int $analyzer_run_id
 * @property int $collection_run_id
 * @property WatchlistRefreshStatus $status
 * @property int $attempt_number
 * @property int $progress_percent
 * @property list<string>|null $warnings
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $previous_video_snapshot_id
 * @property int|null $current_video_snapshot_id
 * @property int|null $previous_channel_snapshot_id
 * @property int|null $current_channel_snapshot_id
 * @property array<string, mixed>|null $deltas
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property WatchlistItem $item
 * @property AnalyzerRun $analyzerRun
 * @property CollectionRun $collectionRun
 */
#[Fillable([
    'watchlist_item_id', 'user_id', 'analyzer_run_id', 'collection_run_id', 'status', 'attempt_number',
    'progress_percent', 'warnings', 'error_code', 'error_message', 'previous_video_snapshot_id',
    'current_video_snapshot_id', 'previous_channel_snapshot_id', 'current_channel_snapshot_id', 'deltas',
    'started_at', 'completed_at', 'failed_at',
])]
class WatchlistRefreshRun extends Model
{
    use HasPublicId;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<WatchlistItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(WatchlistItem::class, 'watchlist_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    /** @return BelongsTo<CollectionRun, $this> */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    protected function casts(): array
    {
        return [
            'status' => WatchlistRefreshStatus::class,
            'attempt_number' => 'integer',
            'progress_percent' => 'integer',
            'warnings' => 'array',
            'deltas' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
