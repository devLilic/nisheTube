<?php

namespace Tests\Unit\Domain\Catalog;

use App\Domain\Catalog\Services\VideoMetricCalculator;
use App\Models\ChannelSnapshot;
use App\Models\Video;
use DateTimeImmutable;
use Tests\TestCase;

class VideoMetricCalculatorTest extends TestCase
{
    public function test_metrics_use_video_age_and_visible_stored_subscriber_counts(): void
    {
        $video = new Video(['published_at' => '2026-08-06 12:00:00']);
        $channelSnapshot = new ChannelSnapshot([
            'subscriber_count' => 250,
            'subscriber_count_hidden' => false,
        ]);

        $metrics = (new VideoMetricCalculator)->calculate(
            $video,
            1000,
            $channelSnapshot,
            new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
        );

        $this->assertSame(172800, $metrics->ageSeconds);
        $this->assertSame('500.000000', $metrics->viewsPerDay);
        $this->assertSame('4.00000000', $metrics->viewsToSubscribersRatio);
    }

    public function test_missing_or_hidden_inputs_remain_null_instead_of_becoming_zero(): void
    {
        $video = new Video(['published_at' => '2026-08-08 12:00:00']);
        $hiddenChannel = new ChannelSnapshot([
            'subscriber_count' => null,
            'subscriber_count_hidden' => true,
        ]);

        $metrics = (new VideoMetricCalculator)->calculate(
            $video,
            null,
            $hiddenChannel,
            new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
        );

        $this->assertSame(0, $metrics->ageSeconds);
        $this->assertNull($metrics->viewsPerDay);
        $this->assertNull($metrics->viewsToSubscribersRatio);
    }

    public function test_zero_views_remain_a_real_zero_when_age_and_subscribers_are_available(): void
    {
        $video = new Video(['published_at' => '2026-08-07 12:00:00']);
        $channelSnapshot = new ChannelSnapshot([
            'subscriber_count' => 250,
            'subscriber_count_hidden' => false,
        ]);

        $metrics = (new VideoMetricCalculator)->calculate(
            $video,
            0,
            $channelSnapshot,
            new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
        );

        $this->assertSame(86400, $metrics->ageSeconds);
        $this->assertSame('0.000000', $metrics->viewsPerDay);
        $this->assertSame('0.00000000', $metrics->viewsToSubscribersRatio);
    }

    public function test_future_publish_times_and_zero_subscribers_do_not_produce_invalid_rates(): void
    {
        $video = new Video(['published_at' => '2026-08-09 12:00:00']);
        $channelSnapshot = new ChannelSnapshot([
            'subscriber_count' => 0,
            'subscriber_count_hidden' => false,
        ]);

        $metrics = (new VideoMetricCalculator)->calculate(
            $video,
            1000,
            $channelSnapshot,
            new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
        );

        $this->assertSame(0, $metrics->ageSeconds);
        $this->assertNull($metrics->viewsPerDay);
        $this->assertNull($metrics->viewsToSubscribersRatio);
    }
}
