<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $provider
 * @property string $provider_channel_id
 * @property string $title
 * @property string|null $custom_url
 * @property string|null $thumbnail_url
 * @property string|null $country
 */
#[Fillable([
    'provider',
    'provider_channel_id',
    'title',
    'custom_url',
    'thumbnail_url',
    'country',
])]
class Channel extends Model
{
    /** @return HasMany<Video, $this> */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /** @return HasMany<ChannelSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ChannelSnapshot::class);
    }
}
