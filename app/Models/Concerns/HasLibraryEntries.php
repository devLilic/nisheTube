<?php

namespace App\Models\Concerns;

use App\Models\Favorite;
use App\Models\Taggable;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLibraryEntries
{
    /** @return MorphMany<Favorite, $this> */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'target');
    }

    /** @return MorphMany<Taggable, $this> */
    public function taggables(): MorphMany
    {
        return $this->morphMany(Taggable::class, 'target');
    }
}
