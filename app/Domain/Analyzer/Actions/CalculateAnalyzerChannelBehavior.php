<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Analyzer\Services\ChannelBehaviorCalculator;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\ChannelSnapshot;
use App\Models\VideoSnapshot;
use DomainException;

final readonly class CalculateAnalyzerChannelBehavior
{
    public function __construct(private ChannelBehaviorCalculator $calculator) {}

    public function handle(AnalyzerRun $run): void
    {
        $memberships = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::ChannelRecentUpload)
            ->with(['video', 'videoSnapshot'])
            ->orderBy('source_position')
            ->get();
        $configurationContext = $run->collectionRun->configuration_context;
        $settings = $configurationContext['channel_behavior'] ?? config('analyzer.channel_behavior');

        if (! is_array($settings)) {
            throw new DomainException('Analyzer channel-behavior configuration is missing.');
        }

        $result = $this->calculator->calculate(
            videos: array_values($memberships->map(fn (AnalyzerRunVideo $membership): array => [
                'views_per_day' => is_numeric($membership->videoSnapshot->views_per_day)
                    ? (float) $membership->videoSnapshot->views_per_day
                    : null,
                'duration_seconds' => $membership->video->duration_seconds,
            ])->all()),
            momentumBlockSize: max(1, (int) ($settings['momentum_block_size'] ?? 5)),
            minimumConsistencySample: max(1, (int) ($settings['minimum_consistency_sample'] ?? 5)),
            minimumCorrelationSample: max(2, (int) ($settings['minimum_correlation_sample'] ?? 5)),
            momentumThresholds: $this->momentumThresholds($settings),
            consistencyThresholds: $this->consistencyThresholds($settings),
            correlationThresholds: $this->correlationThresholds($settings),
        );

        $anchor = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::Anchor)
            ->with(['videoSnapshot', 'channelSnapshot'])
            ->first();
        $previousVideo = $anchor === null ? null : $this->previousVideoSnapshot($run, $anchor);
        $previousChannel = $this->previousChannelSnapshot($run);
        $videoGrowth = $anchor === null ? null : $this->videoGrowth($anchor->videoSnapshot, $previousVideo);
        $channelGrowth = $this->channelGrowth($run->channelSnapshot, $previousChannel);
        $videoMetrics = $run->videoMetrics()->first();
        $channelMetrics = $run->channelMetrics()->firstOrFail();

        if ($videoMetrics !== null && $videoGrowth !== null) {
            $videoMetrics->update([
                ...$videoGrowth,
                'behavior_version' => $run->behavior_version,
                'warnings' => $this->warnings(
                    $videoMetrics->warnings,
                    $previousVideo === null ? ['Observed growth needs a prior owner-scoped snapshot; no pre-first-seen history was inferred.'] : [],
                ),
            ]);
        }
        $channelMetrics->update([
            'momentum_recent_count' => $result->momentumRecentCount,
            'momentum_previous_count' => $result->momentumPreviousCount,
            'momentum_recent_median_views_per_day' => $this->decimalOrNull($result->momentumRecentMedianViewsPerDay, 6),
            'momentum_previous_median_views_per_day' => $this->decimalOrNull($result->momentumPreviousMedianViewsPerDay, 6),
            'momentum_ratio' => $this->decimalOrNull($result->momentumRatio, 8),
            'momentum_class' => $result->momentumClass,
            'consistency_sample_count' => $result->consistencySampleCount,
            'consistency_score' => $this->decimalOrNull($result->consistencyScore, 4),
            'consistency_class' => $result->consistencyClass,
            'duration_performance_sample_count' => $result->durationPerformanceSampleCount,
            'duration_performance_correlation' => $this->decimalOrNull($result->durationPerformanceCorrelation, 8),
            'duration_performance_class' => $result->durationPerformanceClass,
            'duration_performance_buckets' => $result->durationPerformanceBuckets,
            ...$channelGrowth,
            'behavior_version' => $run->behavior_version,
            'warnings' => $this->warnings(
                $channelMetrics->warnings,
                [...$result->warnings, ...($previousChannel === null
                    ? ['Observed channel growth needs a prior owner-scoped snapshot; no pre-first-seen history was inferred.']
                    : [])],
            ),
        ]);
    }

    private function previousVideoSnapshot(AnalyzerRun $run, AnalyzerRunVideo $anchor): ?VideoSnapshot
    {
        return AnalyzerRunVideo::query()
            ->select('analyzer_run_videos.*')
            ->join('video_snapshots', 'video_snapshots.id', '=', 'analyzer_run_videos.video_snapshot_id')
            ->join('analyzer_runs', 'analyzer_runs.id', '=', 'analyzer_run_videos.analyzer_run_id')
            ->where('analyzer_run_videos.video_id', $anchor->video_id)
            ->where('analyzer_run_videos.role', AnalyzerVideoRole::Anchor->value)
            ->where('analyzer_run_videos.analyzer_run_id', '!=', $run->id)
            ->where('analyzer_runs.user_id', $run->user_id)
            ->where('analyzer_runs.status', AnalyzerRunStatus::Completed->value)
            ->where('video_snapshots.collected_at', '<', $anchor->videoSnapshot->collected_at)
            ->orderByDesc('video_snapshots.collected_at')
            ->orderByDesc('video_snapshots.id')
            ->with('videoSnapshot')
            ->first()?->videoSnapshot;
    }

    private function previousChannelSnapshot(AnalyzerRun $run): ?ChannelSnapshot
    {
        if ($run->channelSnapshot === null || $run->channel_id === null) {
            return null;
        }

        return AnalyzerRun::query()
            ->select('analyzer_runs.*')
            ->join('channel_snapshots', 'channel_snapshots.id', '=', 'analyzer_runs.channel_snapshot_id')
            ->where('analyzer_runs.channel_id', $run->channel_id)
            ->where('analyzer_runs.id', '!=', $run->id)
            ->where('analyzer_runs.user_id', $run->user_id)
            ->where('analyzer_runs.status', AnalyzerRunStatus::Completed->value)
            ->where('channel_snapshots.collected_at', '<', $run->channelSnapshot->collected_at)
            ->orderByDesc('channel_snapshots.collected_at')
            ->orderByDesc('channel_snapshots.id')
            ->with('channelSnapshot')
            ->first()?->channelSnapshot;
    }

    /** @return array<string, int|string|null> */
    private function videoGrowth(VideoSnapshot $current, ?VideoSnapshot $previous): array
    {
        $elapsed = $previous === null ? null : (int) $current->collected_at->diffInSeconds($previous->collected_at, true);
        $viewDelta = $this->delta($current->view_count, $previous?->view_count);

        return [
            'previous_video_snapshot_id' => $previous?->id,
            'observed_elapsed_seconds' => $elapsed > 0 ? $elapsed : null,
            'observed_view_delta' => $viewDelta,
            'observed_like_delta' => $this->delta($current->like_count, $previous?->like_count),
            'observed_comment_delta' => $this->delta($current->comment_count, $previous?->comment_count),
            'observed_recent_views_per_day' => $elapsed > 0 && $viewDelta !== null
                ? $this->decimal($viewDelta / ($elapsed / 86400), 6)
                : null,
            'observed_view_growth_percent' => $previous?->view_count !== null && $previous->view_count > 0 && $viewDelta !== null
                ? $this->decimal(($viewDelta / $previous->view_count) * 100, 6)
                : null,
        ];
    }

    /** @return array<string, int|string|null> */
    private function channelGrowth(?ChannelSnapshot $current, ?ChannelSnapshot $previous): array
    {
        $elapsed = $current === null || $previous === null
            ? null
            : (int) $current->collected_at->diffInSeconds($previous->collected_at, true);
        $viewDelta = $this->delta($current?->view_count, $previous?->view_count);

        return [
            'previous_channel_snapshot_id' => $previous?->id,
            'observed_elapsed_seconds' => $elapsed > 0 ? $elapsed : null,
            'observed_view_delta' => $viewDelta,
            'observed_subscriber_delta' => $this->delta($current?->subscriber_count, $previous?->subscriber_count),
            'observed_video_delta' => $this->delta($current?->video_count, $previous?->video_count),
            'observed_view_growth_percent' => $previous?->view_count !== null && $previous->view_count > 0 && $viewDelta !== null
                ? $this->decimal(($viewDelta / $previous->view_count) * 100, 6)
                : null,
        ];
    }

    private function delta(?int $current, ?int $previous): ?int
    {
        return $current === null || $previous === null ? null : $current - $previous;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{declining_max_exclusive: float, stable_max_inclusive: float}
     */
    private function momentumThresholds(array $settings): array
    {
        $thresholds = is_array($settings['momentum_thresholds'] ?? null) ? $settings['momentum_thresholds'] : [];

        return ['declining_max_exclusive' => (float) ($thresholds['declining_max_exclusive'] ?? 0.8), 'stable_max_inclusive' => (float) ($thresholds['stable_max_inclusive'] ?? 1.2)];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{consistent_minimum: float, mixed_minimum: float}
     */
    private function consistencyThresholds(array $settings): array
    {
        $thresholds = is_array($settings['consistency_thresholds'] ?? null) ? $settings['consistency_thresholds'] : [];

        return ['consistent_minimum' => (float) ($thresholds['consistent_minimum'] ?? 75), 'mixed_minimum' => (float) ($thresholds['mixed_minimum'] ?? 50)];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{weak_max_exclusive: float, moderate_max_exclusive: float}
     */
    private function correlationThresholds(array $settings): array
    {
        $thresholds = is_array($settings['correlation_thresholds'] ?? null) ? $settings['correlation_thresholds'] : [];

        return ['weak_max_exclusive' => (float) ($thresholds['weak_max_exclusive'] ?? 0.3), 'moderate_max_exclusive' => (float) ($thresholds['moderate_max_exclusive'] ?? 0.7)];
    }

    /**
     * @param  list<string>|null  $existing
     * @param  list<string>  $additional
     * @return list<string>|null
     */
    private function warnings(?array $existing, array $additional): ?array
    {
        $warnings = array_values(array_unique([...($existing ?? []), ...$additional]));

        return $warnings === [] ? null : $warnings;
    }

    private function decimalOrNull(?float $value, int $scale): ?string
    {
        return $value === null ? null : $this->decimal($value, $scale);
    }

    private function decimal(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }
}
