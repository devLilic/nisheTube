<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\ReadModels\BuildCrossChannelComparison;
use App\Domain\Analyzer\ReadModels\ListComparableChannelAnalyses;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\ChannelAnalysisMetric;
use App\Models\ChannelSnapshot;
use App\Models\SemanticPerformanceAggregate;
use App\Models\SemanticPerformanceProfile;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\ThumbnailPerformanceAggregate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CrossChannelComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_compare_exact_compatible_channel_topic_title_and_thumbnail_cohorts(): void
    {
        $owner = User::factory()->create();
        $before = $this->completedChannelRun($owner, 'Channel Alpha', 10, 1000);
        $after = $this->completedChannelRun($owner, 'Channel Beta', 10, 1500);

        $third = $this->completedChannelRun($owner, 'Channel Gamma', 10, 1800);

        $comparison = app(BuildCrossChannelComparison::class)->handle($owner, $before, $after, $third);

        $this->assertTrue($comparison['compatibility']['channel_metrics']);
        $this->assertTrue($comparison['compatibility']['topics']);
        $this->assertTrue($comparison['compatibility']['title_patterns']);
        $this->assertTrue($comparison['compatibility']['thumbnails']);
        $this->assertSame(1000.0, $comparison['channel_metrics'][0]['values'][0]['value']);
        $this->assertSame(1500.0, $comparison['channel_metrics'][0]['values'][1]['value']);
        $this->assertSame(1800.0, $comparison['channel_metrics'][0]['values'][2]['value']);
        $this->assertSame('Small-space storage', $comparison['topic_rows'][0]['label']);
        $this->assertSame(10, $comparison['topic_rows'][0]['values'][0]['sample_count']);
        $this->assertSame('How-to', $comparison['title_pattern_rows'][0]['label']);
        $this->assertSame('Bright · vivid', $comparison['thumbnail_rows'][0]['label']);
        $this->assertStringContainsString('not an opportunity score', $comparison['disclaimer']);

        $this->actingAs($owner)->get(route('analyzer.compare', [
            'before' => $before->public_id,
            'after' => $after->public_id,
            'third' => $third->public_id,
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('analyzer/compare')
            ->has('options', 3)
            ->where('selected.runs.0.channel_title', 'Channel Alpha')
            ->where('selected.runs.1.channel_title', 'Channel Beta')
            ->where('selected.runs.2.channel_title', 'Channel Gamma')
            ->where('selected.compatibility.topics', true)
            ->where('selected.topic_rows.0.values.2.median_views', 1800));
    }

    public function test_missing_versions_market_time_samples_and_sources_are_guarded_with_explicit_warnings(): void
    {
        $owner = User::factory()->create();
        $before = $this->completedChannelRun($owner, 'Older Channel', 10, 1000, now()->subDays(45));
        $after = $this->completedChannelRun($owner, 'Newer Channel', 4, 800, now());

        ChannelAnalysisMetric::withoutEvents(fn () => $after->channelMetrics()->update([
            'calculation_version' => 'channel-baseline-v2',
            'behavior_version' => 'channel-behavior-v2',
        ]));
        $after->semanticPerformanceProfile()->delete();
        $after->thumbnailAnalysisProfiles()->delete();
        AnalyzerRun::withoutEvents(fn () => $after->update([
            'cache_policy' => CollectionCachePolicy::ForceRefresh,
        ]));

        $comparison = app(BuildCrossChannelComparison::class)->handle($owner, $before->fresh(), $after->fresh());
        $codes = array_column($comparison['compatibility']['warnings'], 'code');

        $this->assertFalse($comparison['compatibility']['channel_metrics']);
        $this->assertFalse($comparison['compatibility']['topics']);
        $this->assertFalse($comparison['compatibility']['title_patterns']);
        $this->assertFalse($comparison['compatibility']['thumbnails']);
        $this->assertContains('channel_model_mismatch', $codes);
        $this->assertContains('market_unknown', $codes);
        $this->assertContains('time_window_mismatch', $codes);
        $this->assertContains('sample_size_mismatch', $codes);
        $this->assertContains('source_policy_mismatch', $codes);
        $this->assertNull($comparison['topic_rows'][0]['values'][1]);
        $this->assertNull($comparison['thumbnail_rows'][0]['values'][1]);
    }

    public function test_selection_is_owner_scoped_distinct_cross_channel_only_and_bounded(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $owned = [];
        for ($index = 0; $index < 26; $index++) {
            $owned[] = $this->completedChannelRun($owner, "Owned {$index}", 2, 100 + $index);
        }
        $foreignRun = $this->completedChannelRun($foreign, 'Foreign', 2, 500);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $options = app(ListComparableChannelAnalyses::class)->handle($owner);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(24, $options);
        $this->assertLessThanOrEqual(8, $queryCount);
        $optionAttemptIds = collect($options)->flatMap(fn (array $option): array => array_column($option['attempts'], 'public_id'));
        $this->assertNotContains($foreignRun->public_id, $optionAttemptIds);
        $this->assertSame(
            collect($options)->pluck('channel_title')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            collect($options)->pluck('channel_title')->values()->all(),
        );
        $this->get(route('analyzer.compare'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('analyzer.compare', [
            'before' => $owned[0]->public_id,
            'after' => $foreignRun->public_id,
        ]))->assertNotFound();

        $sameChannelAttempt = $this->completedChannelRun($owner, 'Repeated channel attempt', 2, 200, now(), $owned[0]->channel);
        $this->actingAs($owner)->get(route('analyzer.compare', [
            'before' => $owned[0]->public_id,
            'after' => $sameChannelAttempt->public_id,
        ]))->assertUnprocessable();

        for ($index = 0; $index < 5; $index++) {
            $this->completedChannelRun($owner, 'Repeated channel attempt', 2, 210 + $index, now()->addMinutes($index + 1), $owned[0]->channel);
        }

        $grouped = collect(app(ListComparableChannelAnalyses::class)->handle($owner))
            ->firstWhere('channel_id', $owned[0]->channel_id);
        $this->assertSame(7, $grouped['attempt_count']);
        $this->assertCount(5, $grouped['attempts']);

        $fourth = $this->completedChannelRun($owner, 'Fourth channel', 2, 300);
        $this->expectException(\DomainException::class);
        app(BuildCrossChannelComparison::class)->handle($owner, $owned[0], $owned[1], $owned[2], $fourth);
    }

    private function completedChannelRun(
        User $owner,
        string $title,
        int $sampleCount,
        int $medianViews,
        mixed $observedAt = null,
        ?Channel $channel = null,
    ): AnalyzerRun {
        $observedAt ??= now();
        $channel ??= Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'comparison-channel-'.uniqid(),
            'title' => $title,
        ]);
        $run = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            $channel->provider_channel_id,
            CollectionCachePolicy::AllowFreshCache,
        );
        $snapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count' => 10000,
            'view_count' => 100000,
            'video_count' => 100,
            'subscriber_count_hidden' => false,
            'collected_at' => $observedAt,
        ]);
        $run->update(['channel_id' => $channel->id, 'channel_snapshot_id' => $snapshot->id]);
        ChannelAnalysisMetric::query()->create([
            'analyzer_run_id' => $run->id,
            'channel_id' => $channel->id,
            'recent_valid_count' => $sampleCount,
            'recent_requested_count' => 30,
            'coverage_percent' => ($sampleCount / 30) * 100,
            'median_views' => $medianViews,
            'median_age_days' => 20,
            'videos_per_month' => 4,
            'duration_distribution' => [],
            'category_distribution' => [],
            'strong_count' => 2,
            'strong_share_percent' => 20,
            'breakout_count' => 1,
            'breakout_share_percent' => 10,
            'threshold_version' => 'video-relative-performance-v1',
            'momentum_recent_count' => 5,
            'momentum_previous_count' => 5,
            'momentum_ratio' => 1.2,
            'consistency_sample_count' => $sampleCount,
            'consistency_score' => 75,
            'duration_performance_sample_count' => $sampleCount,
            'duration_performance_correlation' => 0.4,
            'behavior_version' => 'channel-behavior-v1',
            'calculation_version' => 'channel-baseline-v1',
            'input_summary' => [],
            'calculated_at' => $observedAt,
        ]);
        $semantic = SemanticPerformanceProfile::query()->create([
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'status' => 'complete',
            'provenance' => 'inferred',
            'calculation_version' => 'semantic-performance-v1',
            'topic_version' => 'semantic-title-terms-v1',
            'title_pattern_version' => 'editorial-title-patterns-v1',
            'minimum_sample_size' => 2,
            'cohort_video_count' => $sampleCount,
            'calculated_at' => $observedAt,
        ]);
        $this->semanticAggregate($semantic, 'topic', 'small_space_storage', 'Small-space storage', $sampleCount, $medianViews, 1);
        $this->semanticAggregate($semantic, 'title_pattern', 'how_to', 'How-to', $sampleCount, $medianViews, 2);
        $thumbnail = ThumbnailAnalysisProfile::query()->create([
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'status' => 'complete',
            'provenance' => 'inferred',
            'provider' => 'gd_visual_features',
            'algorithm_version' => 'thumbnail-visual-features-v1',
            'calculation_version' => 'thumbnail-performance-v1',
            'attempt_number' => 1,
            'minimum_sample_size' => 2,
            'cohort_video_count' => $sampleCount,
            'processed_image_count' => $sampleCount,
            'available_image_count' => $sampleCount,
            'reused_image_count' => 0,
            'unavailable_image_count' => 0,
            'calculated_at' => $observedAt,
        ]);
        ThumbnailPerformanceAggregate::query()->create([
            'thumbnail_analysis_profile_id' => $thumbnail->id,
            'cluster_key' => 'bright|vivid',
            'label' => 'Bright · vivid',
            'position' => 1,
            'meets_minimum_sample' => true,
            'sample_count' => $sampleCount,
            'view_sample_count' => $sampleCount,
            'median_views' => $medianViews,
            'average_views' => $medianViews,
            'views_per_day_sample_count' => $sampleCount,
            'median_views_per_day' => 50,
            'average_views_per_day' => 50,
            'breakout_sample_count' => $sampleCount,
            'breakout_count' => 1,
            'breakout_rate_percent' => (1 / $sampleCount) * 100,
            'evidence_video_ids' => [],
        ]);
        $run->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'calculated_at' => $observedAt,
            'completed_at' => $observedAt,
        ]);

        return $run->fresh();
    }

    private function semanticAggregate(
        SemanticPerformanceProfile $profile,
        string $type,
        string $key,
        string $label,
        int $sampleCount,
        int $medianViews,
        int $position,
    ): void {
        SemanticPerformanceAggregate::query()->create([
            'semantic_performance_profile_id' => $profile->id,
            'group_type' => $type,
            'label' => $label,
            'label_key' => $key,
            'is_unclassified' => false,
            'meets_minimum_sample' => true,
            'position' => $position,
            'sample_count' => $sampleCount,
            'view_sample_count' => $sampleCount,
            'median_views' => $medianViews,
            'average_views' => $medianViews,
            'views_per_day_sample_count' => $sampleCount,
            'median_views_per_day' => 50,
            'average_views_per_day' => 50,
            'breakout_sample_count' => $sampleCount,
            'breakout_count' => 1,
            'breakout_rate_percent' => (1 / $sampleCount) * 100,
            'evidence_video_ids' => [],
        ]);
    }
}
