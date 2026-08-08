<?php

namespace App\Models;

use App\Domain\Discovery\Enums\DiscoverySeedSource;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $discovery_run_id
 * @property string $seed_query
 * @property string $seed_key
 * @property DiscoverySeedSource $source
 * @property int|null $research_run_id
 * @property-read DiscoveryRun $discoveryRun
 * @property-read ResearchRun|null $researchRun
 */
#[Fillable(['discovery_run_id', 'seed_query', 'source', 'research_run_id'])]
class DiscoverySeed extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $seed): void {
            $query = Str::squish($seed->seed_query);

            if ($query === '') {
                throw new DomainException('A discovery seed cannot be empty.');
            }

            $seed->seed_query = $query;
            $seed->seed_key = Str::lower($query);
        });
    }

    /** @return BelongsTo<DiscoveryRun, $this> */
    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(DiscoveryRun::class);
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => DiscoverySeedSource::class,
        ];
    }
}
