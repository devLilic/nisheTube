<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $research_run_id
 * @property int $video_id
 * @property int $result_rank
 * @property int $page_number
 * @property int $provider_order
 * @property array<string, mixed>|null $matched_query_metadata
 */
#[Fillable([
    'research_run_id',
    'video_id',
    'result_rank',
    'page_number',
    'provider_order',
    'matched_query_metadata',
])]
class ResearchRunVideo extends Pivot
{
    public $incrementing = false;

    protected $table = 'research_run_videos';

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'result_rank' => 'integer',
            'page_number' => 'integer',
            'provider_order' => 'integer',
            'matched_query_metadata' => 'array',
        ];
    }
}
