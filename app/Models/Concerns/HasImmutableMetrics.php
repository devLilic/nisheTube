<?php

namespace App\Models\Concerns;

use DomainException;
use Illuminate\Database\Eloquent\Model;

trait HasImmutableMetrics
{
    protected static function bootHasImmutableMetrics(): void
    {
        static::updating(function (Model $snapshot): never {
            throw new DomainException('Collected metric snapshots are immutable. Create a new run instead.');
        });
    }
}
