<?php

namespace App\Jobs\Analyzer;

use App\Domain\Thumbnails\Contracts\ThumbnailAnalysisProvider;
use App\Domain\Thumbnails\Contracts\ThumbnailImageFetcher;
use App\Domain\Thumbnails\Enums\ThumbnailAnalysisStatus;
use App\Domain\Thumbnails\Exceptions\ThumbnailImageException;
use App\Models\AnalyzerRunVideo;
use App\Models\ThumbnailAnalysisItem;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\ThumbnailPerformanceAggregate;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Throwable;

final class AnalyzeThumbnails implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $thumbnailAnalysisProfileId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("thumbnail-analysis:{$this->thumbnailAnalysisProfileId}"))->releaseAfter(5)->expireAfter(240)];
    }

    public function uniqueId(): string
    {
        return (string) $this->thumbnailAnalysisProfileId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(ThumbnailImageFetcher $fetcher, ThumbnailAnalysisProvider $provider): void
    {
        $profile = ThumbnailAnalysisProfile::query()->with('analyzerRun')->find($this->thumbnailAnalysisProfileId);
        if ($profile === null || $profile->status->isTerminal()) {
            return;
        }
        if ($profile->analyzerRun->user_id !== $profile->user_id || $profile->analyzerRun->status->value !== 'completed') {
            $profile->update([
                'status' => ThumbnailAnalysisStatus::Failed,
                'error_code' => 'thumbnail_owner_or_run_invalid',
                'error_message' => 'The owned completed Analyzer result is no longer available.',
                'failed_at' => now(),
            ]);

            return;
        }

        if ($profile->status === ThumbnailAnalysisStatus::Queued) {
            $profile->update(['status' => ThumbnailAnalysisStatus::Processing, 'started_at' => now()]);
            $profile = $profile->fresh() ?? $profile;
        }

        $memberships = $profile->analyzerRun->videoMemberships()
            ->with(['video', 'videoSnapshot'])
            ->orderByRaw("CASE WHEN role = 'anchor' THEN 0 ELSE 1 END")
            ->orderBy('source_position')
            ->get();

        foreach ($memberships as $membership) {
            if ($profile->items()->where('analyzer_run_video_id', $membership->id)->exists()) {
                continue;
            }
            $this->analyzeMembership($profile, $membership, $fetcher, $provider);
            $profile->update(['processed_image_count' => $profile->items()->count()]);
        }

        $this->finalize($profile->fresh() ?? $profile);
    }

    private function analyzeMembership(
        ThumbnailAnalysisProfile $profile,
        AnalyzerRunVideo $membership,
        ThumbnailImageFetcher $fetcher,
        ThumbnailAnalysisProvider $provider,
    ): void {
        $url = $membership->video->thumbnail_url;
        $base = [
            'analyzer_run_video_id' => $membership->id,
            'video_id' => $membership->video_id,
            'role' => $membership->role->value,
            'source_url' => $url,
            'source_url_hash' => $url === null ? null : hash('sha256', $url),
            'analyzed_at' => now(),
        ];
        if ($url === null || trim($url) === '') {
            $profile->items()->create([...$base, 'status' => 'unavailable', 'error_code' => 'thumbnail_url_missing']);

            return;
        }

        $cached = $this->cachedItem($profile, $membership, hash('sha256', $url));
        if ($cached !== null) {
            $profile->items()->create([
                ...$base,
                ...$cached->only([
                    'source_checksum', 'mime_type', 'byte_count', 'width', 'height', 'aspect_ratio', 'average_brightness',
                    'average_saturation', 'contrast_score', 'edge_density', 'dominant_color', 'brightness_class',
                    'saturation_class', 'contrast_class', 'composition_class', 'cluster_key', 'confidence_score',
                ]),
                'source_item_id' => $cached->id,
                'status' => 'available',
                'cache_status' => 'reused',
            ]);

            return;
        }

        try {
            $image = $fetcher->fetch($url);
            $features = $provider->analyze($image);
            $profile->items()->create([
                ...$base,
                'status' => 'available',
                'cache_status' => 'fresh',
                'source_checksum' => $image->checksum,
                'mime_type' => $image->mimeType,
                'byte_count' => strlen($image->bytes),
                'width' => $features->width,
                'height' => $features->height,
                'aspect_ratio' => $features->aspectRatio,
                'average_brightness' => $features->averageBrightness,
                'average_saturation' => $features->averageSaturation,
                'contrast_score' => $features->contrastScore,
                'edge_density' => $features->edgeDensity,
                'dominant_color' => $features->dominantColor,
                'brightness_class' => $features->brightnessClass,
                'saturation_class' => $features->saturationClass,
                'contrast_class' => $features->contrastClass,
                'composition_class' => $features->compositionClass,
                'cluster_key' => $features->clusterKey,
                'confidence_score' => $features->confidenceScore,
            ]);
        } catch (ThumbnailImageException $exception) {
            $profile->items()->create([...$base, 'status' => 'unavailable', 'error_code' => $exception->errorCode]);
        }
    }

    private function cachedItem(
        ThumbnailAnalysisProfile $profile,
        AnalyzerRunVideo $membership,
        string $urlHash,
    ): ?ThumbnailAnalysisItem {
        $days = max(1, min(180, (int) config('thumbnails.cache_days', 30)));

        return ThumbnailAnalysisItem::query()
            ->where('video_id', $membership->video_id)
            ->where('source_url_hash', $urlHash)
            ->where('status', 'available')
            ->where('analyzed_at', '>=', now()->subDays($days))
            ->whereHas('profile', fn ($query) => $query
                ->where('user_id', $profile->user_id)
                ->where('provider', $profile->provider)
                ->where('algorithm_version', $profile->algorithm_version))
            ->latest('id')
            ->first();
    }

    private function finalize(ThumbnailAnalysisProfile $profile): void
    {
        $items = $profile->items()->with(['membership.videoSnapshot', 'video'])->get();
        $available = $items->where('status', 'available');
        $cohortAvailable = $available->where('role', 'channel_recent_upload');
        $groups = $cohortAvailable->groupBy('cluster_key');
        $position = 1;
        foreach ($groups->sortByDesc(fn (Collection $group): int => $group->count()) as $clusterKey => $group) {
            $this->storeAggregate($profile, (string) $clusterKey, $group, $position++);
        }

        $minimum = $profile->minimum_sample_size;
        $hasQualifiedCluster = $groups->contains(fn (Collection $group): bool => $group->count() >= $minimum);
        $unavailable = $items->where('status', 'unavailable')->count();
        $warnings = [];
        if ($cohortAvailable->count() < $minimum) {
            $warnings[] = "At least {$minimum} available recent-video thumbnails are required for pattern associations.";
        }
        if (! $hasQualifiedCluster && $cohortAvailable->count() >= $minimum) {
            $warnings[] = "No visual cluster contains the minimum {$minimum} thumbnails; feature evidence remains visible without aggregate claims.";
        }
        if ($unavailable > 0) {
            $warnings[] = "{$unavailable} thumbnail(s) were missing, inaccessible, invalid, or outside the approved image-host boundary.";
        }
        $reused = $items->where('cache_status', 'reused')->count();
        if ($reused > 0) {
            $warnings[] = "{$reused} exact owner-scoped thumbnail feature result(s) were reused from the bounded cache.";
        }

        $status = $cohortAvailable->count() < $minimum || ! $hasQualifiedCluster
            ? ThumbnailAnalysisStatus::Insufficient
            : ($unavailable > 0 ? ThumbnailAnalysisStatus::Partial : ThumbnailAnalysisStatus::Complete);
        $confidenceValues = $available->pluck('confidence_score')->filter(fn ($value): bool => $value !== null)->map(fn ($value): float => (float) $value);
        $coverage = $profile->cohort_video_count === 0 ? 0 : $cohortAvailable->count() / $profile->cohort_video_count;
        $confidence = $confidenceValues->isEmpty()
            ? null
            : round(min(96, $confidenceValues->average() * min(1, $coverage)), 4);

        $profile->update([
            'status' => $status,
            'processed_image_count' => $items->count(),
            'available_image_count' => $available->count(),
            'reused_image_count' => $reused,
            'unavailable_image_count' => $unavailable,
            'confidence_score' => $confidence,
            'warnings' => $warnings === [] ? null : $warnings,
            'calculated_at' => now(),
        ]);
    }

    /** @param Collection<int, ThumbnailAnalysisItem> $items */
    private function storeAggregate(ThumbnailAnalysisProfile $profile, string $clusterKey, Collection $items, int $position): void
    {
        $minimum = $profile->minimum_sample_size;
        $memberships = $items->map(fn (ThumbnailAnalysisItem $item): AnalyzerRunVideo => $item->membership);
        $views = $memberships->pluck('videoSnapshot.view_count')->filter(fn ($value): bool => $value !== null)->map(fn ($value): int => (int) $value)->values()->all();
        $viewsPerDay = $memberships->pluck('videoSnapshot.views_per_day')->filter(fn ($value): bool => $value !== null)->map(fn ($value): float => (float) $value)->values()->all();
        $breakout = $memberships->filter(fn (AnalyzerRunVideo $membership): bool => $membership->breakout_class !== null);
        $breakoutCount = $breakout->filter(fn (AnalyzerRunVideo $membership): bool => $membership->breakout_class === 'breakout')->count();
        $first = $items->first();
        $label = $first === null
            ? $clusterKey
            : implode(' · ', array_map(
                fn (?string $value): string => ucfirst($value ?? 'unknown'),
                [$first->dominant_color, $first->brightness_class, $first->composition_class],
            ));

        ThumbnailPerformanceAggregate::query()->firstOrCreate([
            'thumbnail_analysis_profile_id' => $profile->id,
            'cluster_key' => $clusterKey,
        ], [
            'label' => $label,
            'position' => $position,
            'meets_minimum_sample' => $items->count() >= $minimum,
            'sample_count' => $items->count(),
            'view_sample_count' => count($views),
            'median_views' => count($views) >= $minimum ? $this->median($views) : null,
            'average_views' => count($views) >= $minimum ? round(array_sum($views) / count($views), 4) : null,
            'views_per_day_sample_count' => count($viewsPerDay),
            'median_views_per_day' => count($viewsPerDay) >= $minimum ? $this->median($viewsPerDay) : null,
            'average_views_per_day' => count($viewsPerDay) >= $minimum ? round(array_sum($viewsPerDay) / count($viewsPerDay), 6) : null,
            'breakout_sample_count' => $breakout->count(),
            'breakout_count' => $breakoutCount,
            'breakout_rate_percent' => $breakout->count() >= $minimum ? round(($breakoutCount / $breakout->count()) * 100, 4) : null,
            'evidence_video_ids' => $items->map(fn (ThumbnailAnalysisItem $item): string => $item->video->provider_video_id)->values()->all(),
        ]);
    }

    /** @param array<int, int|float> $values */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return round(count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 6);
    }

    public function failed(?Throwable $exception): void
    {
        $profile = ThumbnailAnalysisProfile::query()->find($this->thumbnailAnalysisProfileId);
        if ($profile === null || $profile->status->isTerminal()) {
            return;
        }
        $profile->update([
            'status' => ThumbnailAnalysisStatus::Failed,
            'error_code' => 'thumbnail_analysis_failed',
            'error_message' => 'Thumbnail analysis failed safely. Retry to create a new immutable attempt.',
            'failed_at' => now(),
        ]);
    }
}
