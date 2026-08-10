<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Models\AnalyzerRun;
use App\Models\VideoAnalysisMetric;

final class CalculateAnalyzerVideoProfile
{
    public function handle(AnalyzerRun $run): VideoAnalysisMetric
    {
        $membership = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::Anchor)
            ->with(['video', 'videoSnapshot', 'channelSnapshot'])
            ->firstOrFail();
        $snapshot = $membership->videoSnapshot;
        $views = $snapshot->view_count;
        $ageSeconds = $snapshot->age_seconds ?? max(
            0,
            $snapshot->collected_at->getTimestamp() - $membership->video->published_at->getTimestamp(),
        );
        $warnings = [];

        if ($views === null) {
            $warnings[] = 'View count is unavailable, so rate calculations that require views are missing.';
        }

        $likeRate = $this->percent($snapshot->like_count, $views);
        $commentRate = $this->percent($snapshot->comment_count, $views);
        $engagementRate = $views !== null && $views > 0 && ($snapshot->like_count !== null || $snapshot->comment_count !== null)
            ? $this->decimal((((float) ($snapshot->like_count ?? 0) + (float) ($snapshot->comment_count ?? 0)) / $views) * 100, 6)
            : null;

        if ($membership->channelSnapshot === null) {
            $warnings[] = 'Author channel statistics are unavailable for this observation.';
        } elseif ($membership->channelSnapshot->subscriber_count_hidden) {
            $warnings[] = 'The author hides subscriber count, so views-to-subscriber ratio is unavailable.';
        }

        return VideoAnalysisMetric::query()->firstOrCreate([
            'analyzer_run_id' => $run->id,
        ], [
            'video_id' => $membership->video_id,
            'age_seconds' => $ageSeconds,
            'lifetime_views_per_day' => $snapshot->views_per_day,
            'views_to_subscribers_ratio' => $snapshot->views_to_subscribers_ratio,
            'like_rate_percent' => $likeRate,
            'comment_rate_percent' => $commentRate,
            'public_engagement_rate_percent' => $engagementRate,
            'calculation_version' => $run->calculation_version,
            'input_summary' => [
                'video_snapshot_id' => $snapshot->id,
                'channel_snapshot_id' => $membership->channel_snapshot_id,
                'observed_at' => $snapshot->collected_at->toIso8601String(),
            ],
            'warnings' => $warnings === [] ? null : $warnings,
            'calculated_at' => now(),
        ]);
    }

    private function percent(?int $numerator, ?int $denominator): ?string
    {
        return $numerator !== null && $denominator !== null && $denominator > 0
            ? $this->decimal(((float) $numerator / $denominator) * 100, 6)
            : null;
    }

    private function decimal(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }
}
