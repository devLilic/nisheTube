<?php

namespace Tests\Unit\Domain\Scoring;

use App\Domain\Scoring\Data\ResearchEvidenceVideoInput;
use App\Domain\Scoring\Services\ResearchEvidenceV1;
use Tests\TestCase;

class ResearchEvidenceV1Test extends TestCase
{
    public function test_it_classifies_strict_related_weak_and_off_topic_with_null_safe_signals(): void
    {
        $result = $this->engine()->calculate('small apartment storage -reaction', 'en', 'long_form', [
            $this->video(1, 'Small apartment storage guide', 100, false),
            $this->video(2, 'Apartment organization ideas', 90, false, semantic: ['small space storage']),
            $this->video(3, 'Small room tour', null, null),
            $this->video(4, 'Small apartment storage reaction', 5000, false),
            $this->video(5, 'Unrelated gaming highlights', 20, true),
        ]);

        $this->assertSame([
            'strictly_relevant', 'related', 'weakly_related', 'off_topic', 'off_topic',
        ], array_column($result['rows'], 'relevance_class'));
        $this->assertSame(1, $result['sample_evidence']['class_counts']['strictly_relevant']);
        $this->assertNull($result['rows'][2]['views_per_day']);
        $this->assertFalse($result['rows'][4]['signals']['format_match']);
        $this->assertSame(['reaction'], $result['rows'][3]['signals']['negative_matches']);
    }

    public function test_it_keeps_formats_separate_and_does_not_deduplicate_duplicate_titles(): void
    {
        $result = $this->engine()->calculate('camera guide', 'en', 'any', [
            $this->video(1, 'Camera guide', 100, true),
            $this->video(2, 'Camera guide', 200, true),
            $this->video(3, 'Camera guide', 10, false),
            $this->video(4, 'Camera guide', 20, false),
            $this->video(5, 'Camera guide', null, null),
        ]);

        $this->assertSame(5, $result['sample_evidence']['full']['count']);
        $this->assertSame(2, $result['format_evidence']['shorts']['count']);
        $this->assertSame(2, $result['format_evidence']['long_form']['count']);
        $this->assertSame(1, $result['format_evidence']['unknown']['count']);
        $this->assertContains(
            'Shorts and long-form evidence are reported separately and are not compared directly.',
            $result['warnings'],
        );
    }

    public function test_it_exposes_robust_percentiles_trimmed_mean_and_top_three_outlier_removals(): void
    {
        $values = [10, 11, 12, 13, 14, 15, 16, 17, 18, 10000];
        $videos = array_map(fn (int $value, int $index): ResearchEvidenceVideoInput => $this->video(
            $index + 1,
            'Camera guide '.$index,
            $value,
            false,
        ), $values, array_keys($values));

        $result = $this->engine()->calculate('camera guide', 'en', 'any', $videos);
        $sample = $result['sample_evidence']['full'];
        $outliers = $result['outlier_evidence']['full'];

        $this->assertSame(14.5, $sample['median_views_per_day']);
        $this->assertSame(14.5, $sample['trimmed_mean_views_per_day']);
        $this->assertSame('high', $outliers['dependency']);
        $this->assertSame([0, 1, 2, 3], array_column($outliers['removals'], 'removed_top_count'));
        $this->assertSame(14.0, $outliers['removals'][1]['median_views_per_day']);
    }

    public function test_it_requires_minimum_samples_for_robust_and_strict_outputs(): void
    {
        $result = $this->engine()->calculate('camera guide', 'en', 'any', [
            $this->video(1, 'Camera overview', 10, false),
            $this->video(2, 'Completely unrelated', null, null),
        ]);

        $this->assertSame('insufficient', $result['sample_evidence']['full']['state']);
        $this->assertNull($result['sample_evidence']['full']['trimmed_mean_views_per_day']);
        $this->assertSame('insufficient', $result['outlier_evidence']['full']['state']);
        $this->assertSame(0, $result['sample_evidence']['strict']['count']);
        $this->assertNull($result['sample_evidence']['strict']['median_views_per_day']);
    }

    public function test_it_labels_compatible_stable_and_unstable_snapshots_deterministically(): void
    {
        $previous = [
            $this->stabilityRow('a', 1, 1, 100),
            $this->stabilityRow('b', 2, 2, 90),
            $this->stabilityRow('c', 3, 3, 80),
            $this->stabilityRow('d', 4, 4, 70),
        ];
        $stable = [
            $this->stabilityRow('a', 1, 1, 105),
            $this->stabilityRow('b', 2, 2, 92),
            $this->stabilityRow('c', 3, 3, 82),
            $this->stabilityRow('d', 4, 4, 72),
        ];
        $unstable = [
            $this->stabilityRow('d', 1, 8, 700),
            $this->stabilityRow('c', 2, 7, 8),
            $this->stabilityRow('b', 3, 6, 900),
            $this->stabilityRow('a', 4, 5, 10),
        ];

        $high = $this->engine()->stability($stable, $previous);
        $low = $this->engine()->stability($unstable, $previous);
        $missing = $this->engine()->stability($stable, []);

        $this->assertSame('high', $high['label']);
        $this->assertSame(1.0, $high['order_stability']);
        $this->assertSame('low', $low['label']);
        $this->assertSame(-1.0, $low['order_stability']);
        $this->assertSame('unavailable', $missing['state']);
        $this->assertNull($missing['label']);
    }

    private function engine(): ResearchEvidenceV1
    {
        return app(ResearchEvidenceV1::class);
    }

    /** @param list<string> $semantic */
    private function video(int $id, string $title, ?float $viewsPerDay, ?bool $short, array $semantic = []): ResearchEvidenceVideoInput
    {
        return new ResearchEvidenceVideoInput(
            videoId: $id,
            channelId: $id,
            videoSnapshotId: $viewsPerDay === null ? null : $id,
            channelSnapshotId: $id,
            providerVideoId: 'video-'.$id,
            title: $title,
            categoryName: null,
            semanticLabels: $semantic,
            topicLabels: [],
            isShort: $short,
            viewsPerDay: $viewsPerDay,
            viewCount: $viewsPerDay === null ? null : (int) ($viewsPerDay * 100),
            resultRank: $id,
        );
    }

    /** @return array<string, mixed> */
    private function stabilityRow(string $id, int $rank, int $channel, float $viewsPerDay): array
    {
        return [
            'provider_video_id' => $id,
            'channel_id' => $channel,
            'result_rank' => $rank,
            'views_per_day' => $viewsPerDay,
        ];
    }
}
