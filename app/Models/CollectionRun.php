<?php

namespace App\Models;

use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $provider
 * @property CollectionRunKind $kind
 * @property CollectionRunStatus $status
 * @property int $attempt_number
 * @property array<string, mixed> $frozen_request
 * @property CollectionCachePolicy $cache_policy
 * @property list<string>|null $requested_parts
 * @property array<string, mixed>|null $configuration_context
 * @property array<string, mixed>|null $safe_metadata
 * @property int $requested_count
 * @property int $processed_count
 * @property int $progress_percent
 * @property list<string>|null $warnings
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 */
#[Fillable([
    'user_id',
    'provider',
    'kind',
    'status',
    'attempt_number',
    'frozen_request',
    'cache_policy',
    'requested_parts',
    'configuration_context',
    'safe_metadata',
    'requested_count',
    'processed_count',
    'progress_percent',
    'warnings',
    'error_code',
    'error_message',
    'started_at',
    'completed_at',
    'failed_at',
])]
class CollectionRun extends Model
{
    use HasPublicId;

    private const FROZEN_ATTRIBUTES = [
        'public_id',
        'user_id',
        'provider',
        'kind',
        'attempt_number',
        'frozen_request',
        'cache_policy',
        'requested_parts',
        'configuration_context',
        'safe_metadata',
        'requested_count',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            $originalStatus = CollectionRunStatus::tryFrom((string) $run->getRawOriginal('status'));

            if ($originalStatus?->isTerminal()) {
                throw new DomainException('Completed and failed collection runs are immutable.');
            }

            if (array_intersect(array_keys($run->getDirty()), self::FROZEN_ATTRIBUTES) !== []) {
                throw new DomainException('Frozen collection request context cannot be changed.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ResearchRun, $this> */
    public function researchRuns(): HasMany
    {
        return $this->hasMany(ResearchRun::class);
    }

    /** @return HasMany<AnalyzerRun, $this> */
    public function analyzerRuns(): HasMany
    {
        return $this->hasMany(AnalyzerRun::class);
    }

    /** @return HasMany<WatchlistRefreshRun, $this> */
    public function watchlistRefreshRuns(): HasMany
    {
        return $this->hasMany(WatchlistRefreshRun::class);
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

    /** @return HasMany<ApiUsageEvent, $this> */
    public function apiUsageEvents(): HasMany
    {
        return $this->hasMany(ApiUsageEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => CollectionRunKind::class,
            'status' => CollectionRunStatus::class,
            'attempt_number' => 'integer',
            'frozen_request' => 'array',
            'cache_policy' => CollectionCachePolicy::class,
            'requested_parts' => 'array',
            'configuration_context' => 'array',
            'safe_metadata' => 'array',
            'requested_count' => 'integer',
            'processed_count' => 'integer',
            'progress_percent' => 'integer',
            'warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
