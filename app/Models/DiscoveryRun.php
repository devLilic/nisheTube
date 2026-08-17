<?php

namespace App\Models;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
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
 * @property string|null $submission_token
 * @property int $user_id
 * @property int|null $research_project_id
 * @property int $market_id
 * @property DiscoveryRunStatus $status
 * @property string $market_key
 * @property string|null $region_code
 * @property string $relevance_language
 * @property array<string, mixed> $parameters
 * @property int $seed_count
 * @property int $candidate_count
 * @property int $progress_percent
 * @property Carbon|null $started_at
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
    'research_project_id',
    'market_id',
    'status',
    'market_key',
    'region_code',
    'relevance_language',
    'parameters',
    'seed_count',
    'candidate_count',
    'progress_percent',
    'started_at',
    'completed_at',
    'failed_at',
    'error_code',
    'error_message',
])]
class DiscoveryRun extends Model
{
    use HasPublicId;

    private const FROZEN_ATTRIBUTES = [
        'public_id',
        'submission_token',
        'user_id',
        'research_project_id',
        'market_id',
        'market_key',
        'region_code',
        'relevance_language',
        'parameters',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            $changed = array_intersect(array_keys($run->getDirty()), self::FROZEN_ATTRIBUTES);

            if ($changed !== []) {
                throw new DomainException('Frozen discovery run parameters cannot be changed.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ResearchProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    /** @return BelongsTo<Market, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /** @return HasMany<DiscoverySeed, $this> */
    public function seeds(): HasMany
    {
        return $this->hasMany(DiscoverySeed::class);
    }

    /** @return HasMany<NicheCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(NicheCandidate::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DiscoveryRunStatus::class,
            'parameters' => 'array',
            'seed_count' => 'integer',
            'candidate_count' => 'integer',
            'progress_percent' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
