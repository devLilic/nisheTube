<?php

namespace App\Models;

use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cleanup_run_id
 * @property CleanupTargetType $target_type
 * @property string $target_reference
 * @property Carbon $original_collection_at
 * @property DeletionOutcome $outcome
 * @property bool $favorite_impacted
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'cleanup_run_id',
    'target_type',
    'target_reference',
    'original_collection_at',
    'outcome',
    'favorite_impacted',
    'deleted_at',
])]
class SnapshotDeletionItem extends Model
{
    /** @return BelongsTo<CleanupRun, $this> */
    public function cleanupRun(): BelongsTo
    {
        return $this->belongsTo(CleanupRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_type' => CleanupTargetType::class,
            'original_collection_at' => 'immutable_datetime',
            'outcome' => DeletionOutcome::class,
            'favorite_impacted' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
