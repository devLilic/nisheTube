<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Data\DerivedVideoMetrics;
use App\Models\ChannelSnapshot;
use App\Models\Video;
use DateTimeImmutable;

class VideoMetricCalculator
{
    public function calculate(
        Video $video,
        ?int $viewCount,
        ?ChannelSnapshot $channelSnapshot,
        DateTimeImmutable $collectedAt,
    ): DerivedVideoMetrics {
        $ageSeconds = max(0, $collectedAt->getTimestamp() - $video->published_at->getTimestamp());
        $viewsPerDay = $viewCount !== null && $ageSeconds > 0
            ? $this->decimal(((float) $viewCount * 86400) / $ageSeconds, 6)
            : null;
        $subscriberCount = $channelSnapshot !== null && ! $channelSnapshot->subscriber_count_hidden
            ? $channelSnapshot->subscriber_count
            : null;
        $viewsToSubscribersRatio = $viewCount !== null && $subscriberCount !== null && $subscriberCount > 0
            ? $this->decimal($viewCount / $subscriberCount, 8)
            : null;

        return new DerivedVideoMetrics($ageSeconds, $viewsPerDay, $viewsToSubscribersRatio);
    }

    private function decimal(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }
}
