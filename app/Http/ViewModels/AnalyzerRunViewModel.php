<?php

namespace App\Http\ViewModels;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Analyzer\Enums\BreakoutClass;
use App\Models\AnalyzerCuration;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\AudienceSignal;
use App\Models\AudienceSignalExclusion;
use App\Models\AudienceSignalProfile;
use App\Models\Channel;
use App\Models\CommentCollectionRun;
use App\Models\PublicComment;
use App\Models\SavedCommentIdea;
use App\Models\SemanticPerformanceProfile;
use App\Models\SemanticTopicProfile;
use App\Models\ThumbnailAnalysisItem;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\ThumbnailPerformanceAggregate;
use App\Models\TranscriptDocument;
use App\Models\TranscriptSegment;
use App\Models\TranscriptStructureInsight;
use App\Models\TranscriptStructureProfile;
use App\Models\UserEntityObservation;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\WatchlistItem;
use Illuminate\Support\Collection;

class AnalyzerRunViewModel
{
    /** @return array<string, mixed> */
    public function toArray(
        AnalyzerRun $run,
        bool $withProfile = true,
        ?Video $displayVideo = null,
        ?Channel $displayChannel = null,
        int $commentPage = 1,
    ): array {
        [$displayVideo, $displayChannel] = $this->displayIdentity($run, $displayVideo, $displayChannel);
        $recentVideoReference = $run->completed_at ?? $run->calculated_at ?? $run->started_at ?? $run->created_at;
        $recentVideoStartsAt = $recentVideoReference?->copy()->subDays(90);
        $displayLabel = $run->target_kind === 'video'
            ? ($displayVideo === null ? ($run->status->isTerminal() ? 'Video title unavailable' : 'Video title pending') : $displayVideo->title)
            : ($displayChannel === null ? ($run->status->isTerminal() ? 'Channel name unavailable' : 'Channel name pending') : $displayChannel->title);
        $data = [
            'public_id' => $run->public_id,
            'target_kind' => $run->target_kind,
            'target_provider_id' => $run->target_provider_id,
            'display_label' => $displayLabel,
            'display_identity' => [
                'video_title' => $displayVideo?->title,
                'channel_title' => $displayChannel?->title,
                'thumbnail_url' => $run->target_kind === 'video'
                    ? $displayVideo?->thumbnail_url
                    : $displayChannel?->thumbnail_url,
                'provider_id' => $run->target_provider_id,
                'is_resolved' => $run->target_kind === 'video' ? $displayVideo !== null : $displayChannel !== null,
            ],
            'status' => $run->status->value,
            'status_label' => $this->statusLabel($run->status),
            'attempt_number' => $run->attempt_number,
            'progress_percent' => $run->progress_percent,
            'cache_policy' => $run->cache_policy->value,
            'origin' => [
                'kind' => $run->origin_kind,
                'reference' => $run->origin_reference,
                'return_url' => $run->navigation_context['return_url'] ?? null,
            ],
            'warnings' => $run->warnings ?? [],
            'is_active' => ! $run->status->isTerminal(),
            'can_refresh' => $run->status->isTerminal(),
            'error' => $this->errorGuidance($run),
            'created_at' => $run->created_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'calculated_at' => $run->calculated_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'recent_video_window' => [
                'days' => 90,
                'reference_at' => $recentVideoReference?->toIso8601String(),
                'starts_at' => $recentVideoStartsAt?->toIso8601String(),
            ],
            'comments' => $this->comments($run, $commentPage),
            'transcript' => $this->transcript($run),
            'thumbnail_analysis' => $this->thumbnailAnalysis($run),
        ];

        if (! $withProfile) {
            return $data;
        }

        $membership = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::Anchor)
            ->with(['video.channel', 'videoSnapshot', 'channelSnapshot'])
            ->first();

        if ($membership === null && ($run->channel === null || $run->channelSnapshot === null)) {
            return [
                ...$data,
                'video' => null,
                'channel' => null,
                'metrics' => null,
                'channel_metrics' => null,
                'recent_videos' => [],
                'cohort' => $this->cohortSummary($run),
                'relative_context' => $this->relativeContext($run),
                'behavior_context' => $this->behaviorContext($run),
                'growth_history' => $this->emptyGrowthHistory($run),
                'topic_profile' => $this->topicProfile($run),
                'topic_performance' => $this->topicPerformance($run),
                'thumbnail_analysis' => $this->thumbnailAnalysis($run),
            ];
        }

        $video = $membership?->video;
        $videoSnapshot = $membership?->videoSnapshot;
        $channel = $run->channel ?? $video?->channel;
        $channelSnapshot = $run->channelSnapshot ?? $membership?->channelSnapshot;
        $category = $video?->category_id === null ? null : VideoCategory::query()
            ->where('provider', $video->provider)
            ->where('category_id', $video->category_id)
            ->where('display_language', 'en')
            ->first();
        $observations = UserEntityObservation::query()
            ->where('user_id', $run->user_id)
            ->whereIn('subject_type', ['video', 'channel'])
            ->whereIn('subject_id', array_values(array_filter([$video?->id, $channel->id])))
            ->get()
            ->keyBy(fn (UserEntityObservation $observation): string => "{$observation->subject_type}:{$observation->subject_id}");
        $videoObservation = $video === null ? null : $observations->get("video:{$video->id}");
        $channelObservation = $observations->get("channel:{$channel->id}");
        $videoSourceMode = $videoSnapshot === null ? null : ($videoSnapshot->collection_run_id === $run->collection_run_id ? 'fresh' : 'cached');
        $channelSourceMode = $channelSnapshot === null ? null : ($channelSnapshot->collection_run_id === $run->collection_run_id ? 'fresh' : 'cached');
        $metrics = $run->videoMetrics()->first();
        $channelMetrics = $run->channelMetrics()->first();
        $videoId = $video === null ? 0 : $video->id;
        $curations = AnalyzerCuration::query()
            ->where('user_id', $run->user_id)
            ->where(function ($query) use ($videoId, $channel): void {
                $query->where(fn ($videoQuery) => $videoQuery
                    ->where('subject_type', 'video')
                    ->where('subject_id', $videoId))
                    ->orWhere(fn ($channelQuery) => $channelQuery
                        ->where('subject_type', 'channel')
                        ->where('subject_id', $channel->id));
            })
            ->get()
            ->keyBy(fn (AnalyzerCuration $curation): string => "{$curation->subject_type}:{$curation->subject_id}");
        $watchlistItem = WatchlistItem::query()
            ->where('user_id', $run->user_id)
            ->where('target_type', $run->target_kind)
            ->where('target_id', $run->target_kind === 'video' ? $video?->id : $channel->id)
            ->first();
        $recentMemberships = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::ChannelRecentUpload)
            ->with(['video', 'videoSnapshot'])
            ->orderBy('source_position')
            ->limit($run->recent_video_limit)
            ->get();
        $recentCategoryNames = VideoCategory::query()
            ->where('provider', 'youtube')
            ->where('display_language', 'en')
            ->whereIn('category_id', $recentMemberships->pluck('video.category_id')->filter()->unique())
            ->pluck('name', 'category_id');
        $localVideoRuns = AnalyzerRun::query()
            ->where('user_id', $run->user_id)
            ->where('target_kind', 'video')
            ->where('status', AnalyzerRunStatus::Completed->value)
            ->whereIn('target_provider_id', $recentMemberships->pluck('video.provider_video_id')->unique())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get(['public_id', 'target_provider_id'])
            ->unique('target_provider_id')
            ->keyBy('target_provider_id');

        return [
            ...$data,
            'video' => $video === null || $videoSnapshot === null ? null : [
                'provider_video_id' => $video->provider_video_id,
                'title' => $video->title,
                'thumbnail_url' => $video->thumbnail_url,
                'youtube_url' => "https://www.youtube.com/watch?v={$video->provider_video_id}",
                'published_at' => $video->published_at->toIso8601String(),
                'duration_seconds' => $video->duration_seconds,
                'category' => $video->category_id === null ? null : [
                    'id' => $video->category_id,
                    'name' => $category?->name,
                ],
                'view_count' => $videoSnapshot->view_count,
                'like_count' => $videoSnapshot->like_count,
                'comment_count' => $videoSnapshot->comment_count,
                'observed_at' => $videoSnapshot->collected_at->toIso8601String(),
                'source_mode' => $videoSourceMode,
                'first_seen_at' => $videoObservation?->first_seen_at?->toIso8601String(),
            ],
            'channel' => [
                'provider_channel_id' => $channel->provider_channel_id,
                'title' => $channel->title,
                'custom_url' => $channel->custom_url,
                'thumbnail_url' => $channel->thumbnail_url,
                'youtube_url' => "https://www.youtube.com/channel/{$channel->provider_channel_id}",
                'country' => $channel->country,
                'subscriber_count' => $channelSnapshot?->subscriber_count,
                'subscriber_count_hidden' => $channelSnapshot->subscriber_count_hidden ?? false,
                'view_count' => $channelSnapshot?->view_count,
                'video_count' => $channelSnapshot?->video_count,
                'observed_at' => $channelSnapshot?->collected_at?->toIso8601String(),
                'source_mode' => $channelSourceMode,
                'first_seen_at' => $channelObservation?->first_seen_at?->toIso8601String(),
            ],
            'metrics' => $metrics === null ? null : [
                'age_seconds' => $metrics->age_seconds,
                'lifetime_views_per_day' => $this->floatOrNull($metrics->lifetime_views_per_day),
                'views_to_subscribers_ratio' => $this->floatOrNull($metrics->views_to_subscribers_ratio),
                'channel_median_ratio' => $this->floatOrNull($metrics->channel_median_ratio),
                'channel_average_ratio' => $this->floatOrNull($metrics->channel_average_ratio),
                'recent_rank' => $metrics->recent_rank,
                'recent_percentile' => $this->floatOrNull($metrics->recent_percentile),
                'recent_comparison_count' => $metrics->recent_comparison_count,
                'breakout_class' => $this->breakoutClass($metrics->breakout_class),
                'threshold_version' => $metrics->threshold_version,
                'previous_video_snapshot_id' => $metrics->previous_video_snapshot_id,
                'observed_elapsed_seconds' => $metrics->observed_elapsed_seconds,
                'observed_view_delta' => $metrics->observed_view_delta,
                'observed_like_delta' => $metrics->observed_like_delta,
                'observed_comment_delta' => $metrics->observed_comment_delta,
                'observed_recent_views_per_day' => $this->floatOrNull($metrics->observed_recent_views_per_day),
                'observed_view_growth_percent' => $this->floatOrNull($metrics->observed_view_growth_percent),
                'behavior_version' => $metrics->behavior_version,
                'like_rate_percent' => $this->floatOrNull($metrics->like_rate_percent),
                'comment_rate_percent' => $this->floatOrNull($metrics->comment_rate_percent),
                'public_engagement_rate_percent' => $this->floatOrNull($metrics->public_engagement_rate_percent),
                'calculation_version' => $metrics->calculation_version,
                'calculated_at' => $metrics->calculated_at->toIso8601String(),
                'warnings' => $metrics->warnings ?? [],
            ],
            'channel_metrics' => $channelMetrics === null ? null : [
                'recent_valid_count' => $channelMetrics->recent_valid_count,
                'recent_requested_count' => $channelMetrics->recent_requested_count,
                'coverage_percent' => $this->floatOrNull($channelMetrics->coverage_percent),
                'median_views' => $this->floatOrNull($channelMetrics->median_views),
                'average_views' => $this->floatOrNull($channelMetrics->average_views),
                'minimum_views' => $channelMetrics->minimum_views,
                'maximum_views' => $channelMetrics->maximum_views,
                'median_likes' => $this->floatOrNull($channelMetrics->median_likes),
                'median_comments' => $this->floatOrNull($channelMetrics->median_comments),
                'median_duration_seconds' => $this->floatOrNull($channelMetrics->median_duration_seconds),
                'average_duration_seconds' => $this->floatOrNull($channelMetrics->average_duration_seconds),
                'minimum_duration_seconds' => $channelMetrics->minimum_duration_seconds,
                'maximum_duration_seconds' => $channelMetrics->maximum_duration_seconds,
                'median_age_days' => $this->floatOrNull($channelMetrics->median_age_days),
                'average_upload_gap_days' => $this->floatOrNull($channelMetrics->average_upload_gap_days),
                'median_upload_gap_days' => $this->floatOrNull($channelMetrics->median_upload_gap_days),
                'longest_upload_gap_days' => $this->floatOrNull($channelMetrics->longest_upload_gap_days),
                'videos_per_week' => $this->floatOrNull($channelMetrics->videos_per_week),
                'videos_per_month' => $this->floatOrNull($channelMetrics->videos_per_month),
                'duration_distribution' => $channelMetrics->duration_distribution,
                'category_distribution' => $channelMetrics->category_distribution,
                'strong_count' => $channelMetrics->strong_count,
                'strong_share_percent' => $this->floatOrNull($channelMetrics->strong_share_percent),
                'breakout_count' => $channelMetrics->breakout_count,
                'breakout_share_percent' => $this->floatOrNull($channelMetrics->breakout_share_percent),
                'threshold_version' => $channelMetrics->threshold_version,
                'momentum_recent_count' => $channelMetrics->momentum_recent_count,
                'momentum_previous_count' => $channelMetrics->momentum_previous_count,
                'momentum_recent_median_views_per_day' => $this->floatOrNull($channelMetrics->momentum_recent_median_views_per_day),
                'momentum_previous_median_views_per_day' => $this->floatOrNull($channelMetrics->momentum_previous_median_views_per_day),
                'momentum_ratio' => $this->floatOrNull($channelMetrics->momentum_ratio),
                'momentum_class' => $channelMetrics->momentum_class,
                'consistency_sample_count' => $channelMetrics->consistency_sample_count,
                'consistency_score' => $this->floatOrNull($channelMetrics->consistency_score),
                'consistency_class' => $channelMetrics->consistency_class,
                'duration_performance_sample_count' => $channelMetrics->duration_performance_sample_count,
                'duration_performance_correlation' => $this->floatOrNull($channelMetrics->duration_performance_correlation),
                'duration_performance_class' => $channelMetrics->duration_performance_class,
                'duration_performance_buckets' => $channelMetrics->duration_performance_buckets ?? [],
                'previous_channel_snapshot_id' => $channelMetrics->previous_channel_snapshot_id,
                'observed_elapsed_seconds' => $channelMetrics->observed_elapsed_seconds,
                'observed_view_delta' => $channelMetrics->observed_view_delta,
                'observed_subscriber_delta' => $channelMetrics->observed_subscriber_delta,
                'observed_video_delta' => $channelMetrics->observed_video_delta,
                'observed_view_growth_percent' => $this->floatOrNull($channelMetrics->observed_view_growth_percent),
                'behavior_version' => $channelMetrics->behavior_version,
                'calculation_version' => $channelMetrics->calculation_version,
                'calculated_at' => $channelMetrics->calculated_at->toIso8601String(),
                'warnings' => $channelMetrics->warnings ?? [],
            ],
            'recent_videos' => $recentMemberships->map(fn ($recent): array => [
                'provider_video_id' => $recent->video->provider_video_id,
                'title' => $recent->video->title,
                'youtube_url' => "https://www.youtube.com/watch?v={$recent->video->provider_video_id}",
                'thumbnail_url' => $recent->video->thumbnail_url,
                'published_at' => $recent->video->published_at->toIso8601String(),
                'published_within_recent_window' => $recentVideoReference !== null
                    && $recentVideoStartsAt !== null
                    && $recent->video->published_at->greaterThanOrEqualTo($recentVideoStartsAt)
                    && $recent->video->published_at->lessThanOrEqualTo($recentVideoReference),
                'source_position' => $recent->source_position,
                'duration_seconds' => $recent->video->duration_seconds,
                'category' => $recent->video->category_id === null ? null : [
                    'id' => $recent->video->category_id,
                    'name' => $recentCategoryNames->get($recent->video->category_id),
                ],
                'view_count' => $recent->videoSnapshot->view_count,
                'like_count' => $recent->videoSnapshot->like_count,
                'comment_count' => $recent->videoSnapshot->comment_count,
                'age_seconds' => $recent->videoSnapshot->age_seconds,
                'lifetime_views_per_day' => $this->floatOrNull($recent->videoSnapshot->views_per_day),
                'observed_at' => $recent->videoSnapshot->collected_at->toIso8601String(),
                'source_mode' => $recent->videoSnapshot->collection_run_id === $run->collection_run_id
                    ? 'fresh'
                    : 'cached',
                'channel_median_ratio' => $this->floatOrNull($recent->channel_median_ratio),
                'breakout_class' => $this->breakoutClass($recent->breakout_class),
                'threshold_version' => $recent->threshold_version,
                'local_analysis' => ($localRun = $localVideoRuns->get($recent->video->provider_video_id)) === null ? null : [
                    'public_id' => $localRun->public_id,
                    'url' => route('analyzer.runs.show', $localRun, false),
                ],
            ])->values()->all(),
            'cohort' => $this->cohortSummary($run),
            'relative_context' => $this->relativeContext($run),
            'behavior_context' => $this->behaviorContext($run),
            'topic_profile' => $this->topicProfile($run),
            'topic_performance' => $this->topicPerformance($run),
            'thumbnail_analysis' => $this->thumbnailAnalysis($run),
            'growth_history' => $membership === null
                ? $this->channelGrowthHistory($run, $channelObservation?->first_seen_at?->toIso8601String())
                : $this->growthHistory($run, $membership, $videoObservation?->first_seen_at?->toIso8601String()),
            'curation' => [
                'video' => $video === null ? null : $this->curation($curations->get("video:{$video->id}")),
                'channel' => $this->curation($curations->get("channel:{$channel->id}")),
            ],
            'handoffs' => [
                'watchlist' => [
                    'available' => true,
                    'target_kind' => $run->target_kind,
                    'target_reference' => $run->target_provider_id,
                    'watchlist_public_id' => $watchlistItem?->public_id,
                ],
                'topic_workspace' => ['available' => true, 'target_kind' => 'analyzer_run', 'target_reference' => $run->public_id],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function thumbnailAnalysis(AnalyzerRun $run): array
    {
        $profile = ThumbnailAnalysisProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->with(['items.video', 'aggregates'])
            ->latest('id')
            ->first();
        if ($profile === null) {
            return [
                'status' => 'not_requested',
                'is_active' => false,
                'can_analyze' => $run->status === AnalyzerRunStatus::Completed,
                'profile' => null,
            ];
        }

        return [
            'status' => $profile->status->value,
            'is_active' => $profile->status->isActive(),
            'can_analyze' => $run->status === AnalyzerRunStatus::Completed && $profile->status->value === 'failed',
            'profile' => [
                'public_id' => $profile->public_id,
                'status' => $profile->status->value,
                'provenance' => $profile->provenance,
                'provider' => $profile->provider,
                'algorithm_version' => $profile->algorithm_version,
                'calculation_version' => $profile->calculation_version,
                'attempt_number' => $profile->attempt_number,
                'minimum_sample_size' => $profile->minimum_sample_size,
                'cohort_video_count' => $profile->cohort_video_count,
                'processed_image_count' => $profile->processed_image_count,
                'available_image_count' => $profile->available_image_count,
                'reused_image_count' => $profile->reused_image_count,
                'unavailable_image_count' => $profile->unavailable_image_count,
                'confidence_score' => $this->floatOrNull($profile->confidence_score),
                'warnings' => $profile->warnings ?? [],
                'error_code' => $profile->error_code,
                'error_message' => $profile->error_message,
                'started_at' => $profile->started_at?->toIso8601String(),
                'calculated_at' => $profile->calculated_at?->toIso8601String(),
                'failed_at' => $profile->failed_at?->toIso8601String(),
                'items' => $profile->items->map(fn (ThumbnailAnalysisItem $item): array => [
                    'provider_video_id' => $item->video->provider_video_id,
                    'title' => $item->video->title,
                    'thumbnail_url' => $item->source_url,
                    'role' => $item->role,
                    'status' => $item->status,
                    'cache_status' => $item->cache_status,
                    'width' => $item->width,
                    'height' => $item->height,
                    'aspect_ratio' => $this->floatOrNull($item->aspect_ratio),
                    'average_brightness' => $this->floatOrNull($item->average_brightness),
                    'average_saturation' => $this->floatOrNull($item->average_saturation),
                    'contrast_score' => $this->floatOrNull($item->contrast_score),
                    'edge_density' => $this->floatOrNull($item->edge_density),
                    'dominant_color' => $item->dominant_color,
                    'brightness_class' => $item->brightness_class,
                    'saturation_class' => $item->saturation_class,
                    'contrast_class' => $item->contrast_class,
                    'composition_class' => $item->composition_class,
                    'cluster_key' => $item->cluster_key,
                    'confidence_score' => $this->floatOrNull($item->confidence_score),
                    'error_code' => $item->error_code,
                    'analyzed_at' => $item->analyzed_at?->toIso8601String(),
                ])->values()->all(),
                'aggregates' => $profile->aggregates->map(fn (ThumbnailPerformanceAggregate $aggregate): array => [
                    'cluster_key' => $aggregate->cluster_key,
                    'label' => $aggregate->label,
                    'meets_minimum_sample' => $aggregate->meets_minimum_sample,
                    'sample_count' => $aggregate->sample_count,
                    'view_sample_count' => $aggregate->view_sample_count,
                    'median_views' => $this->floatOrNull($aggregate->median_views),
                    'average_views' => $this->floatOrNull($aggregate->average_views),
                    'views_per_day_sample_count' => $aggregate->views_per_day_sample_count,
                    'median_views_per_day' => $this->floatOrNull($aggregate->median_views_per_day),
                    'average_views_per_day' => $this->floatOrNull($aggregate->average_views_per_day),
                    'breakout_sample_count' => $aggregate->breakout_sample_count,
                    'breakout_count' => $aggregate->breakout_count,
                    'breakout_rate_percent' => $this->floatOrNull($aggregate->breakout_rate_percent),
                    'evidence_video_ids' => $aggregate->evidence_video_ids,
                ])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function comments(AnalyzerRun $run, int $requestedPage = 1): array
    {
        $collection = CommentCollectionRun::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->latest('id')
            ->first();

        if ($collection === null) {
            return [
                'status' => 'not_requested',
                'is_active' => false,
                'can_collect' => $run->target_kind === 'video' && $run->status === AnalyzerRunStatus::Completed,
                'items' => [],
                'pagination' => $this->commentPagination(1, 10, 0),
                'retention_cutoff_at' => null,
                'audience_signals' => null,
            ];
        }

        $perPage = 10;
        $total = $collection->comments()->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $requestedPage), $lastPage);
        $commentItems = $collection->comments()
            ->orderByDesc('like_count')
            ->orderBy('id')
            ->forPage($currentPage, $perPage)
            ->get();
        $savedIdeas = SavedCommentIdea::query()
            ->where('user_id', $run->user_id)
            ->where('video_id', $collection->video_id)
            ->whereIn('provider_comment_id', $commentItems->pluck('provider_comment_id'))
            ->get(['public_id', 'provider_comment_id'])
            ->keyBy('provider_comment_id');

        $audienceProfile = AudienceSignalProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->where('comment_collection_run_id', $collection->id)
            ->with('signals.evidenceComments')
            ->latest('id')
            ->first();

        return [
            'public_id' => $collection->public_id,
            'status' => $collection->status->value,
            'is_active' => $collection->status->isActive(),
            'can_collect' => $run->target_kind === 'video' && $run->status === AnalyzerRunStatus::Completed && ! $collection->status->isActive(),
            'max_comments' => $collection->max_comments,
            'pages_collected' => $collection->pages_collected,
            'comments_collected' => $collection->comments_collected,
            'reported_total_results' => $collection->reported_total_results,
            'has_more' => $collection->next_page_token !== null,
            'reply_scope' => $collection->reply_scope,
            'error_code' => $collection->error_code,
            'error_message' => $collection->error_message,
            'collected_at' => $collection->collected_at?->toIso8601String(),
            'retention_cutoff_at' => $collection->collected_at?->addMonthsNoOverflow(max(1, (int) config('retention.months', 6)))->toIso8601String(),
            'audience_signals' => $this->audienceSignals($audienceProfile),
            'pagination' => $this->commentPagination($currentPage, $perPage, $total),
            'items' => $commentItems->map(fn (PublicComment $comment): array => [
                'id' => $comment->id,
                'text' => $comment->text,
                'like_count' => $comment->like_count,
                'reply_count' => $comment->reply_count,
                'published_at' => $comment->published_at?->toIso8601String(),
                'updated_at' => $comment->provider_updated_at?->toIso8601String(),
                'is_saved' => $savedIdeas->has($comment->provider_comment_id),
                'saved_idea_public_id' => $savedIdeas->get($comment->provider_comment_id)?->public_id,
            ])->values()->all(),
        ];
    }

    /** @return array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null} */
    private function commentPagination(int $currentPage, int $perPage, int $total): array
    {
        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? null : (($currentPage - 1) * $perPage) + 1,
            'to' => $total === 0 ? null : min($currentPage * $perPage, $total),
        ];
    }

    /** @return array<string, mixed>|null */
    private function audienceSignals(?AudienceSignalProfile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $exclusions = AudienceSignalExclusion::query()
            ->where('user_id', $profile->user_id)
            ->where('is_active', true)
            ->orderBy('display_word')
            ->get();
        $excludedKeys = $exclusions->pluck('normalized_word')->flip();
        $visibleSignals = $profile->signals
            ->reject(fn (AudienceSignal $signal): bool => $excludedKeys->has($signal->label_key))
            ->values();

        return [
            'public_id' => $profile->public_id,
            'status' => $profile->status,
            'provenance' => $profile->provenance,
            'provider' => $profile->provider,
            'algorithm_version' => $profile->algorithm_version,
            'language' => $profile->language,
            'comment_count' => $profile->comment_count,
            'usable_comment_count' => $profile->usable_comment_count,
            'confidence_score' => $this->floatOrNull($profile->confidence_score),
            'warnings' => $profile->warnings ?? [],
            'calculated_at' => $profile->calculated_at->toIso8601String(),
            'hidden_signal_count' => $profile->signals->count() - $visibleSignals->count(),
            'excluded_words' => $exclusions->map(fn (AudienceSignalExclusion $exclusion): array => [
                'public_id' => $exclusion->public_id,
                'word' => $exclusion->display_word,
                'excluded_at' => $exclusion->excluded_at->toIso8601String(),
            ])->values()->all(),
            'signals' => $visibleSignals->map(fn (AudienceSignal $signal): array => [
                'kind' => $signal->kind,
                'label' => $signal->label,
                'can_exclude' => preg_match('/^[\p{L}\p{N}][\p{L}\p{N}-]*$/u', $signal->label_key) === 1,
                'confidence' => $this->floatOrNull($signal->confidence),
                'comment_count' => $signal->comment_count,
                'occurrence_count' => $signal->occurrence_count,
                'evidence' => $signal->evidenceComments->map(fn (PublicComment $comment): array => [
                    'comment_id' => $comment->id,
                    'text' => $comment->text,
                    'published_at' => $comment->published_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function transcript(AnalyzerRun $run): array
    {
        $document = TranscriptDocument::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->with(['segments', 'structureProfiles.insights'])
            ->latest('id')
            ->first();

        if ($document === null) {
            return [
                'status' => 'not_provided',
                'can_manage' => $run->target_kind === 'video' && $run->status === AnalyzerRunStatus::Completed,
                'document' => null,
            ];
        }

        return [
            'status' => $document->status,
            'can_manage' => $run->target_kind === 'video' && $run->status === AnalyzerRunStatus::Completed,
            'document' => [
                'public_id' => $document->public_id,
                'provider' => $document->provider,
                'provider_version' => $document->provider_version,
                'input_format' => $document->input_format,
                'language' => $document->language,
                'character_count' => $document->character_count,
                'segment_count' => $document->segment_count,
                'warnings' => $document->warnings ?? [],
                'provided_at' => $document->provided_at->toIso8601String(),
                'retention_cutoff_at' => $document->provided_at
                    ->addMonthsNoOverflow(max(1, (int) config('retention.months', 6)))
                    ->toIso8601String(),
                'revision_count' => TranscriptDocument::query()
                    ->where('user_id', $run->user_id)
                    ->where('analyzer_run_id', $run->id)
                    ->count(),
                'analysis' => $this->transcriptStructure($document),
                'segments' => $document->segments->map(fn (TranscriptSegment $segment): array => [
                    'position' => $segment->position,
                    'start_ms' => $segment->start_ms,
                    'end_ms' => $segment->end_ms,
                    'text' => $segment->text,
                ])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function transcriptStructure(TranscriptDocument $document): ?array
    {
        /** @var TranscriptStructureProfile|null $profile */
        $profile = $document->structureProfiles
            ->sortByDesc('id')
            ->first();
        if ($profile === null) {
            return null;
        }

        return [
            'public_id' => $profile->public_id,
            'status' => $profile->status,
            'provenance' => $profile->provenance,
            'provider' => $profile->provider,
            'algorithm_version' => $profile->algorithm_version,
            'language' => $profile->language,
            'word_count' => $profile->word_count,
            'evidence_count' => $profile->evidence_count,
            'confidence_score' => $this->floatOrNull($profile->confidence_score),
            'warnings' => $profile->warnings ?? [],
            'calculated_at' => $profile->calculated_at->toIso8601String(),
            'insights' => $profile->insights->map(fn (TranscriptStructureInsight $insight): array => [
                'kind' => $insight->kind,
                'label' => $insight->label,
                'detail' => $insight->detail,
                'confidence' => $this->floatOrNull($insight->confidence),
                'position' => $insight->position,
                'start_offset' => $insight->start_offset,
                'end_offset' => $insight->end_offset,
                'start_ms' => $insight->start_ms,
                'end_ms' => $insight->end_ms,
                'evidence_text' => mb_substr(
                    $document->plain_text,
                    $insight->start_offset,
                    max(0, $insight->end_offset - $insight->start_offset),
                    'UTF-8',
                ),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, AnalyzerRun>  $runs
     * @return list<array<string, mixed>>
     */
    public function summaries(Collection $runs): array
    {
        $videos = Video::query()
            ->with('channel')
            ->where('provider', 'youtube')
            ->whereIn('provider_video_id', $runs->where('target_kind', 'video')->pluck('target_provider_id')->unique())
            ->get()
            ->keyBy('provider_video_id');
        $channels = Channel::query()
            ->where('provider', 'youtube')
            ->whereIn('provider_channel_id', $runs->where('target_kind', 'channel')->pluck('target_provider_id')->unique())
            ->get()
            ->keyBy('provider_channel_id');

        $summaries = [];
        foreach ($runs as $run) {
            $summaries[] = $this->toArray(
                $run,
                false,
                $run->target_kind === 'video' ? $videos->get($run->target_provider_id) : null,
                $run->target_kind === 'channel' ? $channels->get($run->target_provider_id) : null,
            );
        }

        return $summaries;
    }

    /** @return array{0: Video|null, 1: Channel|null} */
    private function displayIdentity(AnalyzerRun $run, ?Video $video, ?Channel $channel): array
    {
        if ($run->target_kind === 'video') {
            $video ??= $run->video_id === null
                ? Video::query()->with('channel')->where('provider', 'youtube')->where('provider_video_id', $run->target_provider_id)->first()
                : $run->video()->with('channel')->first();
            $channel ??= $video?->channel;
        } else {
            $channel ??= $run->channel_id === null
                ? Channel::query()->where('provider', 'youtube')->where('provider_channel_id', $run->target_provider_id)->first()
                : $run->channel;
        }

        return [$video, $channel];
    }

    private function statusLabel(AnalyzerRunStatus $status): string
    {
        return match ($status) {
            AnalyzerRunStatus::Queued => 'Queued',
            AnalyzerRunStatus::FetchingVideo => 'Fetching video',
            AnalyzerRunStatus::FetchingChannel => 'Fetching channel',
            AnalyzerRunStatus::LoadingRecentVideos => 'Loading recent videos',
            AnalyzerRunStatus::CalculatingMetrics => 'Calculating metrics',
            AnalyzerRunStatus::SavingAnalysis => 'Saving analysis',
            AnalyzerRunStatus::Completed => 'Completed',
            AnalyzerRunStatus::Failed => 'Failed',
        };
    }

    /** @return array<string, string>|null */
    private function errorGuidance(AnalyzerRun $run): ?array
    {
        if ($run->status !== AnalyzerRunStatus::Failed || $run->error_code === null) {
            return null;
        }

        [$title, $guidance, $action] = match ($run->error_code) {
            'youtube_video_not_found' => ['Video unavailable', 'Check the URL or ID. YouTube did not distinguish private, removed, or inaccessible content.', 'new_analysis'],
            'youtube_channel_not_found' => ['Channel unavailable', 'Check the URL or ID. YouTube did not distinguish removed or inaccessible content.', 'new_analysis'],
            'youtube_key_missing', 'youtube_key_invalid', 'youtube_api_disabled' => ['YouTube connection needs attention', 'Review the local YouTube integration settings, then retry.', 'settings'],
            'youtube_quota_exhausted' => ['YouTube quota is exhausted', 'Wait for the Pacific Time reset shown in the quota widget, then retry.', 'settings'],
            'youtube_rate_limited', 'youtube_unavailable' => ['YouTube is temporarily unavailable', 'Wait briefly and retry this immutable attempt.', 'retry'],
            default => ['Analysis could not finish', 'The run record is safe. Retry or review the local integration settings.', 'retry'],
        };

        return [
            'code' => $run->error_code,
            'title' => $title,
            'message' => $run->error_message ?? 'The analysis could not be completed.',
            'guidance' => $guidance,
            'action' => $action,
        ];
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** @return array<string, mixed>|null */
    private function topicProfile(AnalyzerRun $run): ?array
    {
        $profile = SemanticTopicProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->with('classifications')
            ->first();
        if ($profile === null) {
            return null;
        }

        $labels = fn (string $kind): array => $profile->classifications
            ->where('kind', $kind)
            ->sortBy('position')
            ->map(fn ($classification): array => [
                'label' => $classification->label,
                'key' => $classification->label_key,
                'confidence' => $this->floatOrNull($classification->confidence),
                'evidence_video_ids' => $classification->evidence_video_ids ?? [],
            ])->values()->all();

        return [
            'public_id' => $profile->public_id,
            'status' => $profile->status,
            'provenance' => $profile->provenance,
            'provider' => $profile->provider,
            'algorithm_version' => $profile->algorithm_version,
            'language' => $profile->language,
            'niche' => $profile->niche_label === null ? null : [
                'label' => $profile->niche_label,
                'key' => $profile->niche_key,
                'confidence' => $this->floatOrNull($profile->niche_confidence),
            ],
            'subniche' => $profile->subniche_label === null ? null : [
                'label' => $profile->subniche_label,
                'key' => $profile->subniche_key,
                'confidence' => $this->floatOrNull($profile->subniche_confidence),
            ],
            'topics' => $labels('topic'),
            'content_pillars' => $labels('content_pillar'),
            'concentration_score' => $this->floatOrNull($profile->concentration_score),
            'confidence_score' => $this->floatOrNull($profile->confidence_score),
            'evidence_video_count' => (int) ($profile->evidence_summary['video_count'] ?? 0),
            'warnings' => $profile->warnings ?? [],
            'calculated_at' => $profile->calculated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function topicPerformance(AnalyzerRun $run): ?array
    {
        $profile = SemanticPerformanceProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->with('aggregates')
            ->first();
        if ($profile === null) {
            return null;
        }

        return [
            'public_id' => $profile->public_id,
            'status' => $profile->status,
            'provenance' => $profile->provenance,
            'calculation_version' => $profile->calculation_version,
            'topic_version' => $profile->topic_version,
            'title_pattern_version' => $profile->title_pattern_version,
            'minimum_sample_size' => $profile->minimum_sample_size,
            'cohort_video_count' => $profile->cohort_video_count,
            'warnings' => $profile->warnings ?? [],
            'calculated_at' => $profile->calculated_at->toIso8601String(),
            'aggregates' => $profile->aggregates->map(fn ($aggregate): array => [
                'group_type' => $aggregate->group_type,
                'label' => $aggregate->label,
                'key' => $aggregate->label_key,
                'is_unclassified' => $aggregate->is_unclassified,
                'meets_minimum_sample' => $aggregate->meets_minimum_sample,
                'sample_count' => $aggregate->sample_count,
                'view_sample_count' => $aggregate->view_sample_count,
                'median_views' => $this->floatOrNull($aggregate->median_views),
                'average_views' => $this->floatOrNull($aggregate->average_views),
                'views_per_day_sample_count' => $aggregate->views_per_day_sample_count,
                'median_views_per_day' => $this->floatOrNull($aggregate->median_views_per_day),
                'average_views_per_day' => $this->floatOrNull($aggregate->average_views_per_day),
                'breakout_sample_count' => $aggregate->breakout_sample_count,
                'breakout_count' => $aggregate->breakout_count,
                'breakout_rate_percent' => $this->floatOrNull($aggregate->breakout_rate_percent),
                'evidence_video_ids' => $aggregate->evidence_video_ids ?? [],
            ])->values()->all(),
        ];
    }

    /** @return array<string, int|bool|string|null> */
    private function cohortSummary(AnalyzerRun $run): array
    {
        return [
            'requested_limit' => $run->recent_video_limit,
            'playlist_item_count' => $run->cohortItems()->count(),
            'unavailable_count' => $run->cohortItems()->whereNotNull('unavailable_at')->count(),
            'collection_complete' => $run->cohort_collection_complete,
            'uploads_playlist_id' => $run->uploads_playlist_id,
        ];
    }

    /** @return array{value: string, label: string}|null */
    private function breakoutClass(?string $value): ?array
    {
        $class = $value === null ? null : BreakoutClass::tryFrom($value);

        return $class === null ? null : ['value' => $class->value, 'label' => $class->label()];
    }

    /** @return array<string, float|int|string|null> */
    private function relativeContext(AnalyzerRun $run): array
    {
        return [
            'threshold_version' => $run->threshold_version,
            'minimum_baseline_count' => max(1, (int) config('analyzer.relative_performance.minimum_baseline_count', 3)),
            'strong_ratio' => (float) config('analyzer.relative_performance.thresholds.above_average_max_exclusive', 3),
            'breakout_ratio_exclusive' => (float) config('analyzer.relative_performance.thresholds.strong_max_inclusive', 5),
        ];
    }

    /** @return array<string, float|int|string> */
    private function behaviorContext(AnalyzerRun $run): array
    {
        $frozen = $run->collectionRun->configuration_context['channel_behavior'] ?? [];
        $momentumThresholds = is_array($frozen['momentum_thresholds'] ?? null)
            ? $frozen['momentum_thresholds']
            : [];

        return [
            'version' => $run->behavior_version,
            'momentum_block_size' => max(1, (int) ($frozen['momentum_block_size'] ?? config('analyzer.channel_behavior.momentum_block_size', 5))),
            'minimum_consistency_sample' => max(1, (int) ($frozen['minimum_consistency_sample'] ?? config('analyzer.channel_behavior.minimum_consistency_sample', 5))),
            'minimum_correlation_sample' => max(2, (int) ($frozen['minimum_correlation_sample'] ?? config('analyzer.channel_behavior.minimum_correlation_sample', 5))),
            'declining_below' => (float) ($momentumThresholds['declining_max_exclusive'] ?? config('analyzer.channel_behavior.momentum_thresholds.declining_max_exclusive', 0.8)),
            'growing_above' => (float) ($momentumThresholds['stable_max_inclusive'] ?? config('analyzer.channel_behavior.momentum_thresholds.stable_max_inclusive', 1.2)),
        ];
    }

    /** @return array{first_seen_at: string|null, retention_cutoff_at: string, points: list<array<string, int|float|string|null>>} */
    private function emptyGrowthHistory(AnalyzerRun $run): array
    {
        return [
            'first_seen_at' => null,
            'retention_cutoff_at' => now()->subMonthsNoOverflow(max(1, (int) config('retention.months', 6)))->toIso8601String(),
            'points' => [],
        ];
    }

    /** @return array{first_seen_at: string|null, retention_cutoff_at: string, points: list<array<string, int|float|string|null>>} */
    private function growthHistory(AnalyzerRun $run, AnalyzerRunVideo $current, ?string $firstSeenAt): array
    {
        $limit = max(2, min(100, (int) config('analyzer.channel_behavior.history_limit', 24)));
        $memberships = AnalyzerRunVideo::query()
            ->select('analyzer_run_videos.*')
            ->join('analyzer_runs', 'analyzer_runs.id', '=', 'analyzer_run_videos.analyzer_run_id')
            ->join('video_snapshots', 'video_snapshots.id', '=', 'analyzer_run_videos.video_snapshot_id')
            ->where('analyzer_runs.user_id', $run->user_id)
            ->where('analyzer_runs.status', AnalyzerRunStatus::Completed->value)
            ->where('analyzer_run_videos.video_id', $current->video_id)
            ->where('analyzer_run_videos.role', AnalyzerVideoRole::Anchor->value)
            ->where('video_snapshots.collected_at', '<=', $current->videoSnapshot->collected_at)
            ->orderByDesc('video_snapshots.collected_at')
            ->orderByDesc('analyzer_runs.id')
            ->limit($limit * 3)
            ->with(['analyzerRun', 'videoSnapshot', 'channelSnapshot'])
            ->get()
            ->unique('video_snapshot_id')
            ->take($limit)
            ->sortBy(fn (AnalyzerRunVideo $membership): int => $membership->videoSnapshot->collected_at->getTimestamp())
            ->values();
        $points = [];
        $previous = null;

        foreach ($memberships as $historyMembership) {
            $snapshot = $historyMembership->videoSnapshot;
            $channelSnapshot = $historyMembership->channelSnapshot;
            $elapsed = $previous === null
                ? null
                : $snapshot->collected_at->diffInSeconds($previous->videoSnapshot->collected_at, true);
            $viewDelta = $this->countDelta($snapshot->view_count, $previous?->videoSnapshot->view_count);

            $points[] = [
                'attempt_public_id' => $historyMembership->analyzerRun->public_id,
                'observed_at' => $snapshot->collected_at->toIso8601String(),
                'video_snapshot_id' => $snapshot->id,
                'view_count' => $snapshot->view_count,
                'like_count' => $snapshot->like_count,
                'comment_count' => $snapshot->comment_count,
                'channel_view_count' => $channelSnapshot?->view_count,
                'channel_subscriber_count' => $channelSnapshot?->subscriber_count,
                'channel_video_count' => $channelSnapshot?->video_count,
                'elapsed_seconds' => $elapsed > 0 ? $elapsed : null,
                'view_delta' => $viewDelta,
                'like_delta' => $this->countDelta($snapshot->like_count, $previous?->videoSnapshot->like_count),
                'comment_delta' => $this->countDelta($snapshot->comment_count, $previous?->videoSnapshot->comment_count),
                'observed_recent_views_per_day' => $elapsed > 0 && $viewDelta !== null
                    ? $viewDelta / ($elapsed / 86400)
                    : null,
                'view_growth_percent' => $previous?->videoSnapshot->view_count !== null
                    && $previous->videoSnapshot->view_count > 0
                    && $viewDelta !== null
                        ? ($viewDelta / $previous->videoSnapshot->view_count) * 100
                        : null,
            ];
            $previous = $historyMembership;
        }

        return [
            'first_seen_at' => $firstSeenAt,
            'retention_cutoff_at' => now()->subMonthsNoOverflow(max(1, (int) config('retention.months', 6)))->toIso8601String(),
            'points' => $points,
        ];
    }

    private function countDelta(?int $current, ?int $previous): ?int
    {
        return $current === null || $previous === null ? null : $current - $previous;
    }

    /** @return array{research_status: string, note: string|null} */
    private function curation(?AnalyzerCuration $curation): array
    {
        return [
            'research_status' => $curation === null ? 'unreviewed' : $curation->research_status,
            'note' => $curation === null ? null : $curation->note,
        ];
    }

    /** @return array{first_seen_at: string|null, retention_cutoff_at: string, points: list<array<string, int|float|string|null>>} */
    private function channelGrowthHistory(AnalyzerRun $run, ?string $firstSeenAt): array
    {
        $limit = max(2, min(100, (int) config('analyzer.channel_behavior.history_limit', 24)));
        $runs = AnalyzerRun::query()
            ->select('analyzer_runs.*')
            ->join('channel_snapshots', 'channel_snapshots.id', '=', 'analyzer_runs.channel_snapshot_id')
            ->where('analyzer_runs.user_id', $run->user_id)
            ->where('analyzer_runs.channel_id', $run->channel_id)
            ->where('analyzer_runs.status', AnalyzerRunStatus::Completed->value)
            ->where('channel_snapshots.collected_at', '<=', $run->channelSnapshot->collected_at)
            ->with('channelSnapshot')
            ->orderByDesc('channel_snapshots.collected_at')
            ->orderByDesc('analyzer_runs.id')
            ->limit($limit * 3)
            ->get()
            ->unique('channel_snapshot_id')
            ->take($limit)
            ->sortBy(fn (AnalyzerRun $historyRun): int => $historyRun->channelSnapshot->collected_at->getTimestamp())
            ->values();
        $points = [];
        $previous = null;

        foreach ($runs as $historyRun) {
            $snapshot = $historyRun->channelSnapshot;
            $elapsed = $previous === null ? null : $snapshot->collected_at->diffInSeconds($previous->channelSnapshot->collected_at, true);
            $points[] = [
                'attempt_public_id' => $historyRun->public_id,
                'observed_at' => $snapshot->collected_at->toIso8601String(),
                'video_snapshot_id' => null,
                'view_count' => null,
                'like_count' => null,
                'comment_count' => null,
                'channel_view_count' => $snapshot->view_count,
                'channel_subscriber_count' => $snapshot->subscriber_count,
                'channel_video_count' => $snapshot->video_count,
                'elapsed_seconds' => $elapsed > 0 ? $elapsed : null,
                'view_delta' => $this->countDelta($snapshot->view_count, $previous?->channelSnapshot->view_count),
                'like_delta' => null,
                'comment_delta' => null,
                'observed_recent_views_per_day' => null,
                'view_growth_percent' => null,
            ];
            $previous = $historyRun;
        }

        return [
            'first_seen_at' => $firstSeenAt,
            'retention_cutoff_at' => now()->subMonthsNoOverflow(max(1, (int) config('retention.months', 6)))->toIso8601String(),
            'points' => $points,
        ];
    }
}
