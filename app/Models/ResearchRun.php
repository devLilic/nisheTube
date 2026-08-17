<?php

namespace App\Models;

use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Concerns\HasLibraryEntries;
use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string|null $submission_token
 * @property int $user_id
 * @property int $research_query_id
 * @property int|null $collection_run_id
 * @property ResearchRunKind $kind
 * @property ResearchRunStatus $status
 * @property int $attempt_number
 * @property string $query_text
 * @property string $market_key
 * @property string|null $region_code
 * @property string $relevance_language
 * @property array<string, mixed> $parameters
 * @property int $requested_result_count
 * @property int $collected_result_count
 * @property int $enriched_result_count
 * @property int $progress_percent
 * @property list<string>|null $collection_warnings
 * @property Carbon|null $started_at
 * @property Carbon|null $search_completed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'submission_token',
    'research_query_id',
    'collection_run_id',
    'kind',
    'status',
    'attempt_number',
    'query_text',
    'market_key',
    'region_code',
    'relevance_language',
    'parameters',
    'requested_result_count',
    'collected_result_count',
    'enriched_result_count',
    'progress_percent',
    'collection_warnings',
    'started_at',
    'search_completed_at',
    'completed_at',
    'failed_at',
    'error_code',
    'error_message',
])]
class ResearchRun extends Model
{
    use HasLibraryEntries, HasPublicId;

    private const FROZEN_ATTRIBUTES = [
        'public_id',
        'submission_token',
        'user_id',
        'research_query_id',
        'collection_run_id',
        'kind',
        'attempt_number',
        'query_text',
        'market_key',
        'region_code',
        'relevance_language',
        'parameters',
        'requested_result_count',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            $originalStatusValue = $run->getRawOriginal('status');
            $originalStatus = is_string($originalStatusValue)
                ? ResearchRunStatus::tryFrom($originalStatusValue)
                : null;

            if ($originalStatus?->isTerminal()) {
                throw new DomainException('Completed and failed research runs are immutable.');
            }

            $changedFrozenAttributes = array_intersect(
                array_keys($run->getDirty()),
                self::FROZEN_ATTRIBUTES,
            );

            if ($changedFrozenAttributes !== []) {
                throw new DomainException('Frozen research run parameters cannot be changed.');
            }
        });

        static::deleting(function (self $run): void {
            $run->setRelation('collectionRunPendingCleanup', $run->collectionRun()->first());
        });

        static::deleted(function (self $run): void {
            /** @var CollectionRun|null $collectionRun */
            $collectionRun = $run->getRelation('collectionRunPendingCleanup');

            if (
                $collectionRun !== null
                && ! $collectionRun->researchRuns()->exists()
                && ! $collectionRun->videoSnapshots()->exists()
                && ! $collectionRun->channelSnapshots()->exists()
            ) {
                $collectionRun->delete();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ResearchQuery, $this> */
    public function researchQuery(): BelongsTo
    {
        return $this->belongsTo(ResearchQuery::class, 'research_query_id');
    }

    /** @return BelongsTo<CollectionRun, $this> */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    /** @return HasMany<ResearchRunSearchPage, $this> */
    public function searchPages(): HasMany
    {
        return $this->hasMany(ResearchRunSearchPage::class);
    }

    /** @return HasMany<ResearchRunSearchResult, $this> */
    public function searchResults(): HasMany
    {
        return $this->hasMany(ResearchRunSearchResult::class);
    }

    /** @return BelongsToMany<Video, $this, ResearchRunVideo, 'pivot'> */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'research_run_videos')
            ->using(ResearchRunVideo::class)
            ->withPivot([
                'video_snapshot_id',
                'channel_snapshot_id',
                'result_rank',
                'page_number',
                'provider_order',
                'matched_query_metadata',
            ])
            ->withTimestamps();
    }

    /** @return HasMany<ResearchRunVideo, $this> */
    public function videoMemberships(): HasMany
    {
        return $this->hasMany(ResearchRunVideo::class);
    }

    /** @return HasMany<VideoSnapshot, $this> */
    public function videoSnapshots(): HasMany
    {
        return $this->hasMany(VideoSnapshot::class);
    }

    /** @return HasMany<ChannelSnapshot, $this> */
    public function channelSnapshots(): HasMany
    {
        return $this->hasMany(ChannelSnapshot::class);
    }

    /** @return HasMany<OpportunityScore, $this> */
    public function opportunityScores(): HasMany
    {
        return $this->hasMany(OpportunityScore::class);
    }

    /** @return HasMany<ProfitabilityFitScore, $this> */
    public function profitabilityFitScores(): HasMany
    {
        return $this->hasMany(ProfitabilityFitScore::class);
    }

    /** @return HasMany<ResearchEvidenceProfile, $this> */
    public function evidenceProfiles(): HasMany
    {
        return $this->hasMany(ResearchEvidenceProfile::class);
    }

    /** @return HasMany<DiscoverySeed, $this> */
    public function discoverySeeds(): HasMany
    {
        return $this->hasMany(DiscoverySeed::class);
    }

    /** @return HasMany<NicheCandidate, $this> */
    public function validationCandidates(): HasMany
    {
        return $this->hasMany(NicheCandidate::class, 'validation_research_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ResearchRunKind::class,
            'status' => ResearchRunStatus::class,
            'attempt_number' => 'integer',
            'parameters' => 'array',
            'requested_result_count' => 'integer',
            'collected_result_count' => 'integer',
            'enriched_result_count' => 'integer',
            'progress_percent' => 'integer',
            'collection_warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'search_completed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
