<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $research_run_id
 * @property string $provider_video_id
 * @property string $provider_channel_id
 * @property string $title
 * @property Carbon $published_at
 * @property int $result_rank
 * @property int $page_number
 * @property int $provider_order
 */
#[Fillable([
    'research_run_id',
    'provider_video_id',
    'provider_channel_id',
    'title',
    'published_at',
    'result_rank',
    'page_number',
    'provider_order',
])]
class ResearchRunSearchResult extends Model
{
    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'result_rank' => 'integer',
            'page_number' => 'integer',
            'provider_order' => 'integer',
        ];
    }
}
