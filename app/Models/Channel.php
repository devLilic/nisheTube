<?php

namespace App\Models;

use App\Models\Concerns\HasLibraryEntries;
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
    use HasLibraryEntries;

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

    /** @return HasMany<AnalyzerRun, $this> */
    public function analyzerRuns(): HasMany
    {
        return $this->hasMany(AnalyzerRun::class);
    }

    /** @return HasMany<WatchlistItem, $this> */
    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class, 'target_id')->where('target_type', 'channel');
    }
}
