<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $subject_type
 * @property int $subject_id
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon $last_fetched_at
 * @property int|null $first_video_snapshot_id
 * @property int|null $latest_video_snapshot_id
 * @property int|null $first_channel_snapshot_id
 * @property int|null $latest_channel_snapshot_id
 * @property int|null $first_observed_count
 */
#[Fillable([
    'user_id', 'subject_type', 'subject_id', 'first_seen_at', 'last_seen_at', 'last_fetched_at',
    'first_video_snapshot_id', 'latest_video_snapshot_id', 'first_channel_snapshot_id',
    'latest_channel_snapshot_id', 'first_observed_count',
])]
class UserEntityObservation extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'last_fetched_at' => 'immutable_datetime',
            'first_observed_count' => 'integer',
        ];
    }
}
