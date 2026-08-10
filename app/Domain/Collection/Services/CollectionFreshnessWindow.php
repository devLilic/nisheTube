<?php

namespace App\Domain\Collection\Services;

use App\Models\CollectionRun;

final class CollectionFreshnessWindow
{
    public function normalize(?int $seconds): int
    {
        $default = max(1, (int) config('collection.freshness.default_seconds', 21_600));
        $minimum = max(1, (int) config('collection.freshness.minimum_seconds', 300));
        $maximum = max($minimum, (int) config('collection.freshness.maximum_seconds', 604_800));

        return min($maximum, max($minimum, $seconds ?? $default));
    }

    public function for(CollectionRun $run): int
    {
        $configured = $run->configuration_context['freshness_window_seconds'] ?? null;

        return $this->normalize(is_numeric($configured) ? (int) $configured : null);
    }
}
