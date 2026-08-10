<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\ChannelAnalysisMetric;
use App\Models\VideoCategory;
use Illuminate\Support\Collection;

final class CalculateChannelBaseline
{
    public function handle(AnalyzerRun $run): ChannelAnalysisMetric
    {
        $memberships = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::ChannelRecentUpload)
            ->with(['video', 'videoSnapshot'])
            ->orderBy('source_position')
            ->get();
        $requested = $run->cohortItems()->count();
        $warnings = [];

        if ($memberships->isEmpty()) {
            $warnings[] = 'No valid recent uploads were available for channel baseline calculations.';
        }

        if ($requested < $run->recent_video_limit) {
            $warnings[] = "The uploads playlist contained {$requested} of the requested {$run->recent_video_limit} recent items.";
        }

        $views = $this->numeric($memberships->pluck('videoSnapshot.view_count'));
        $likes = $this->numeric($memberships->pluck('videoSnapshot.like_count'));
        $comments = $this->numeric($memberships->pluck('videoSnapshot.comment_count'));
        $durations = $this->numeric($memberships->pluck('video.duration_seconds'));
        $ages = $memberships->map(fn ($membership): float => max(
            0,
            abs($membership->videoSnapshot->collected_at->getTimestamp() - $membership->video->published_at->getTimestamp()) / 86400,
        ))->values();
        $published = $memberships->pluck('video.published_at')->sortDesc()->values();
        $gaps = collect();

        for ($index = 1; $index < $published->count(); $index++) {
            $gaps->push(abs($published[$index - 1]->getTimestamp() - $published[$index]->getTimestamp()) / 86400);
        }

        if ($views->count() < $memberships->count()) {
            $warnings[] = 'Some recent uploads have no public view count and were excluded from view baselines.';
        }

        if ($durations->count() < $memberships->count()) {
            $warnings[] = 'Some recent uploads have no duration and were excluded from duration baselines.';
        }

        $categories = $this->categoryDistribution($memberships);
        $spanDays = $published->count() > 1
            ? abs($published->first()->getTimestamp() - $published->last()->getTimestamp()) / 86400
            : null;
        $uploadRate = $spanDays !== null && $spanDays > 0 ? ($published->count() - 1) / $spanDays : null;
        $calculatedAt = now();

        return ChannelAnalysisMetric::query()->firstOrCreate([
            'analyzer_run_id' => $run->id,
        ], [
            'channel_id' => $run->channel_id,
            'recent_valid_count' => $memberships->count(),
            'recent_requested_count' => $run->recent_video_limit,
            'coverage_percent' => $run->recent_video_limit > 0
                ? $this->decimal(($memberships->count() / $run->recent_video_limit) * 100, 4)
                : '0.0000',
            'median_views' => $this->decimalOrNull($this->median($views), 4),
            'average_views' => $this->decimalOrNull($this->average($views), 4),
            'minimum_views' => $views->isEmpty() ? null : (int) $views->min(),
            'maximum_views' => $views->isEmpty() ? null : (int) $views->max(),
            'median_likes' => $this->decimalOrNull($this->median($likes), 4),
            'median_comments' => $this->decimalOrNull($this->median($comments), 4),
            'median_duration_seconds' => $this->decimalOrNull($this->median($durations), 4),
            'average_duration_seconds' => $this->decimalOrNull($this->average($durations), 4),
            'minimum_duration_seconds' => $durations->isEmpty() ? null : (int) $durations->min(),
            'maximum_duration_seconds' => $durations->isEmpty() ? null : (int) $durations->max(),
            'median_age_days' => $this->decimalOrNull($this->median($ages), 4),
            'average_upload_gap_days' => $this->decimalOrNull($this->average($gaps), 6),
            'median_upload_gap_days' => $this->decimalOrNull($this->median($gaps), 6),
            'longest_upload_gap_days' => $gaps->isEmpty() ? null : $this->decimal((float) $gaps->max(), 6),
            'videos_per_week' => $uploadRate === null ? null : $this->decimal($uploadRate * 7, 6),
            'videos_per_month' => $uploadRate === null ? null : $this->decimal($uploadRate * 30.4375, 6),
            'duration_distribution' => $this->durationDistribution($durations),
            'category_distribution' => $categories,
            'calculation_version' => (string) config('analyzer.channel_calculation_version', 'channel-baseline-v1'),
            'input_summary' => [
                'membership_ids' => $memberships->pluck('id')->all(),
                'video_snapshot_ids' => $memberships->pluck('video_snapshot_id')->all(),
                'recent_video_limit' => $run->recent_video_limit,
                'playlist_item_count' => $requested,
            ],
            'warnings' => $warnings === [] ? null : $warnings,
            'calculated_at' => $calculatedAt,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return Collection<int, float>
     */
    private function numeric(Collection $values): Collection
    {
        return $values->filter(static fn ($value): bool => is_numeric($value))
            ->map(static fn ($value): float => (float) $value)
            ->sort()
            ->values();
    }

    /** @param Collection<int, float> $values */
    private function median(Collection $values): ?float
    {
        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /** @param Collection<int, float> $values */
    private function average(Collection $values): ?float
    {
        return $values->isEmpty() ? null : (float) $values->average();
    }

    /**
     * @param  Collection<int, float>  $durations
     * @return array<string, int>
     */
    private function durationDistribution(Collection $durations): array
    {
        $buckets = ['under_5' => 0, '5_to_10' => 0, '10_to_20' => 0, '20_to_40' => 0, '40_plus' => 0];

        foreach ($durations as $seconds) {
            $key = match (true) {
                $seconds < 300 => 'under_5',
                $seconds < 600 => '5_to_10',
                $seconds < 1200 => '10_to_20',
                $seconds < 2400 => '20_to_40',
                default => '40_plus',
            };
            $buckets[$key]++;
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, AnalyzerRunVideo>  $memberships
     * @return list<array{id: string, name: string|null, count: int}>
     */
    private function categoryDistribution(Collection $memberships): array
    {
        $counts = $memberships->pluck('video.category_id')
            ->filter(static fn ($value): bool => is_string($value) && $value !== '')
            ->countBy();
        $names = VideoCategory::query()
            ->where('provider', 'youtube')
            ->where('display_language', 'en')
            ->whereIn('category_id', $counts->keys())
            ->pluck('name', 'category_id');

        return array_values($counts->map(function (int $count, string $id) use ($names): array {
            $name = $names->get($id);

            return [
                'id' => $id,
                'name' => is_string($name) ? $name : null,
                'count' => $count,
            ];
        })->sortByDesc('count')->values()->all());
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
