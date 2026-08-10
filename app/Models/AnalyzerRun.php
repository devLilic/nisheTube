<?php

namespace App\Models;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $target_kind
 * @property string $target_provider_id
 * @property int|null $video_id
 * @property int|null $channel_id
 * @property int|null $channel_snapshot_id
 * @property int $collection_run_id
 * @property int $recent_video_limit
 * @property string|null $uploads_playlist_id
 * @property string|null $cohort_next_page_token
 * @property bool $cohort_collection_complete
 * @property string $origin_kind
 * @property string|null $origin_reference
 * @property array{return_url: string}|null $navigation_context
 * @property CollectionCachePolicy $cache_policy
 * @property int $freshness_window_seconds
 * @property string $calculation_version
 * @property string $threshold_version
 * @property string|null $behavior_version
 * @property AnalyzerRunStatus $status
 * @property int $attempt_number
 * @property int $progress_percent
 * @property list<string>|null $warnings
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $calculated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property CollectionRun $collectionRun
 */
#[Fillable([
    'user_id', 'target_kind', 'target_provider_id', 'video_id', 'channel_id', 'channel_snapshot_id', 'collection_run_id',
    'origin_kind', 'origin_reference', 'navigation_context', 'cache_policy', 'freshness_window_seconds', 'recent_video_limit',
    'uploads_playlist_id', 'cohort_next_page_token', 'cohort_collection_complete', 'calculation_version',
    'threshold_version', 'behavior_version',
    'status', 'attempt_number', 'progress_percent', 'warnings', 'error_code', 'error_message',
    'started_at', 'calculated_at', 'completed_at', 'failed_at',
])]
class AnalyzerRun extends Model
{
    use HasPublicId;

    private const FROZEN_ATTRIBUTES = [
        'public_id', 'user_id', 'target_kind', 'target_provider_id', 'collection_run_id',
        'origin_kind', 'origin_reference', 'navigation_context', 'cache_policy', 'freshness_window_seconds',
        'recent_video_limit', 'calculation_version', 'threshold_version', 'behavior_version', 'attempt_number',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            $status = AnalyzerRunStatus::tryFrom((string) $run->getRawOriginal('status'));

            if ($status?->isTerminal()) {
                throw new DomainException('Completed and failed Analyzer runs are immutable.');
            }

            if (array_intersect(array_keys($run->getDirty()), self::FROZEN_ATTRIBUTES) !== []) {
                throw new DomainException('Frozen Analyzer request context cannot be changed.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CollectionRun, $this> */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<ChannelSnapshot, $this> */
    public function channelSnapshot(): BelongsTo
    {
        return $this->belongsTo(ChannelSnapshot::class);
    }

    /** @return HasMany<AnalyzerRunVideo, $this> */
    public function videoMemberships(): HasMany
    {
        return $this->hasMany(AnalyzerRunVideo::class);
    }

    /** @return HasOne<VideoAnalysisMetric, $this> */
    public function videoMetrics(): HasOne
    {
        return $this->hasOne(VideoAnalysisMetric::class);
    }

    /** @return HasOne<ChannelAnalysisMetric, $this> */
    public function channelMetrics(): HasOne
    {
        return $this->hasOne(ChannelAnalysisMetric::class);
    }

    /** @return HasOne<SemanticTopicProfile, $this> */
    public function semanticTopicProfile(): HasOne
    {
        return $this->hasOne(SemanticTopicProfile::class);
    }

    /** @return HasOne<SemanticPerformanceProfile, $this> */
    public function semanticPerformanceProfile(): HasOne
    {
        return $this->hasOne(SemanticPerformanceProfile::class);
    }

    /** @return HasMany<AnalyzerRunCohortItem, $this> */
    public function cohortItems(): HasMany
    {
        return $this->hasMany(AnalyzerRunCohortItem::class);
    }

    /** @return HasMany<CommentCollectionRun, $this> */
    public function commentCollections(): HasMany
    {
        return $this->hasMany(CommentCollectionRun::class);
    }

    /** @return HasMany<AudienceSignalProfile, $this> */
    public function audienceSignalProfiles(): HasMany
    {
        return $this->hasMany(AudienceSignalProfile::class);
    }

    /** @return HasMany<TranscriptDocument, $this> */
    public function transcriptDocuments(): HasMany
    {
        return $this->hasMany(TranscriptDocument::class);
    }

    /** @return HasMany<TranscriptStructureProfile, $this> */
    public function transcriptStructureProfiles(): HasMany
    {
        return $this->hasMany(TranscriptStructureProfile::class);
    }

    /** @return HasMany<ThumbnailAnalysisProfile, $this> */
    public function thumbnailAnalysisProfiles(): HasMany
    {
        return $this->hasMany(ThumbnailAnalysisProfile::class);
    }

    protected function casts(): array
    {
        return [
            'cache_policy' => CollectionCachePolicy::class,
            'navigation_context' => 'array',
            'status' => AnalyzerRunStatus::class,
            'freshness_window_seconds' => 'integer',
            'recent_video_limit' => 'integer',
            'cohort_collection_complete' => 'boolean',
            'attempt_number' => 'integer',
            'progress_percent' => 'integer',
            'warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
