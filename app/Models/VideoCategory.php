<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $category_id
 * @property string $region_key
 * @property string $display_language
 * @property string $name
 * @property bool $assignable
 * @property Carbon|null $fetched_at
 * @property Carbon|null $expires_at
 */
#[Fillable([
    'provider', 'category_id', 'region_key', 'display_language', 'name', 'assignable', 'fetched_at', 'expires_at',
])]
class VideoCategory extends Model
{
    protected function casts(): array
    {
        return ['assignable' => 'boolean', 'fetched_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
