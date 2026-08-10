<?php

namespace App\Domain\Analyzer\ReadModels;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Models\AnalyzerRun;
use App\Models\SemanticPerformanceProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ListComparableChannelAnalyses
{
    private const CHANNEL_LIMIT = 24;

    private const ATTEMPT_LIMIT_PER_CHANNEL = 5;

    /** @return list<array<string, mixed>> */
    public function handle(User $user): array
    {
        $channelRows = AnalyzerRun::query()
            ->where('user_id', $user->id)
            ->where('status', AnalyzerRunStatus::Completed->value)
            ->whereNotNull('channel_id')
            ->whereNotNull('channel_snapshot_id')
            ->whereHas('channelMetrics')
            ->select('channel_id')
            ->selectRaw('COUNT(*) as attempt_count')
            ->selectRaw('MAX(completed_at) as latest_completed_at')
            ->groupBy('channel_id')
            ->orderByDesc('latest_completed_at')
            ->orderByDesc('channel_id')
            ->limit(self::CHANNEL_LIMIT)
            ->get();

        if ($channelRows->isEmpty()) {
            return [];
        }

        $channelIds = $channelRows->pluck('channel_id')->map(fn (mixed $id): int => (int) $id);
        $rankedAttempts = AnalyzerRun::query()
            ->where('user_id', $user->id)
            ->where('status', AnalyzerRunStatus::Completed->value)
            ->whereIn('channel_id', $channelIds)
            ->whereNotNull('channel_snapshot_id')
            ->whereHas('channelMetrics')
            ->select(['id', 'channel_id'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY channel_id ORDER BY completed_at DESC, id DESC) as attempt_position');

        $attemptIds = DB::query()
            ->fromSub($rankedAttempts->toBase(), 'ranked_attempts')
            ->where('attempt_position', '<=', self::ATTEMPT_LIMIT_PER_CHANNEL)
            ->pluck('id');

        $attempts = AnalyzerRun::query()
            ->whereIn('id', $attemptIds)
            ->with([
                'channel:id,title,provider_channel_id',
                'channelSnapshot:id,collected_at',
                'channelMetrics:id,analyzer_run_id,recent_valid_count,calculation_version,behavior_version,threshold_version',
                'semanticPerformanceProfile:id,analyzer_run_id,status,calculation_version,topic_version,title_pattern_version,cohort_video_count',
            ])
            ->withExists(['thumbnailAnalysisProfiles as has_thumbnail_performance' => fn ($query) => $query
                ->whereIn('status', ['complete', 'partial', 'insufficient'])])
            ->latest('completed_at')
            ->latest('id')
            ->get();

        $attemptCounts = $channelRows->mapWithKeys(fn (AnalyzerRun $row): array => [
            (int) $row->channel_id => (int) $row->getAttribute('attempt_count'),
        ]);

        return array_values($attempts
            ->groupBy('channel_id')
            ->map(function ($runs, int|string $channelId) use ($attemptCounts): array {
                /** @var AnalyzerRun $latest */
                $latest = $runs->first();

                return [
                    'channel_id' => (int) $channelId,
                    'channel_title' => $latest->channel->title,
                    'provider_channel_id' => $latest->channel->provider_channel_id,
                    'attempt_count' => (int) $attemptCounts->get((int) $channelId, $runs->count()),
                    'attempts' => $runs->map(fn (AnalyzerRun $run): array => [
                        'public_id' => $run->public_id,
                        'observed_at' => $run->channelSnapshot->collected_at->toIso8601String(),
                        'completed_at' => $run->completed_at?->toIso8601String(),
                        'cohort_video_count' => $run->channelMetrics->recent_valid_count,
                        'has_topic_performance' => $run->getRelationValue('semanticPerformanceProfile') instanceof SemanticPerformanceProfile,
                        'has_thumbnail_performance' => (bool) $run->getAttribute('has_thumbnail_performance'),
                    ])->values()->all(),
                ];
            })
            ->sort(fn (array $left, array $right): int => strnatcasecmp($left['channel_title'], $right['channel_title']))
            ->values()
            ->all());
    }
}
