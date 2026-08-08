<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $research_run_id
 * @property int $page_number
 * @property string|null $request_page_token
 * @property string|null $next_page_token
 * @property int $result_count
 * @property int|null $approximate_total_results
 * @property list<string>|null $warnings
 */
#[Fillable([
    'research_run_id',
    'page_number',
    'request_page_token',
    'next_page_token',
    'result_count',
    'approximate_total_results',
    'warnings',
])]
class ResearchRunSearchPage extends Model
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
            'page_number' => 'integer',
            'result_count' => 'integer',
            'approximate_total_results' => 'integer',
            'warnings' => 'array',
        ];
    }
}
