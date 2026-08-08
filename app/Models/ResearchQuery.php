<?php

namespace App\Models;

use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Models\Concerns\HasLibraryEntries;
use App\Models\Concerns\HasPublicId;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $research_project_id
 * @property int $market_id
 * @property string $query_text
 * @property string $query_key
 * @property SearchOrder $search_order
 * @property Carbon|null $published_after
 * @property Carbon|null $published_before
 * @property VideoDurationFilter|null $video_duration
 * @property string|null $video_category_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'research_project_id',
    'market_id',
    'query_text',
    'search_order',
    'published_after',
    'published_before',
    'video_duration',
    'video_category_id',
])]
class ResearchQuery extends Model
{
    use HasLibraryEntries, HasPublicId;

    protected static function booted(): void
    {
        static::saving(function (self $query): void {
            $queryText = Str::squish($query->query_text);

            if ($queryText === '') {
                throw new DomainException('A research query cannot be empty.');
            }

            $query->query_text = $queryText;
            $query->query_key = Str::lower($queryText);
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

    /** @return HasMany<ResearchRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ResearchRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'search_order' => SearchOrder::class,
            'published_after' => 'immutable_datetime',
            'published_before' => 'immutable_datetime',
            'video_duration' => VideoDurationFilter::class,
        ];
    }
}
