<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['analyzer_run_id', 'provider_video_id', 'source_position', 'enriched_at', 'unavailable_at'])]
class AnalyzerRunCohortItem extends Model
{
    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    protected function casts(): array
    {
        return [
            'source_position' => 'integer',
            'enriched_at' => 'immutable_datetime',
            'unavailable_at' => 'immutable_datetime',
        ];
    }
}
