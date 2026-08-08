<?php

namespace App\Models;

use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $research_run_id
 * @property string $provider
 * @property string $quota_bucket
 * @property string $endpoint
 * @property int $request_count
 * @property int $estimated_cost
 * @property QuotaUsageOutcome $outcome
 * @property string|null $safe_error_code
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'research_run_id',
    'provider',
    'quota_bucket',
    'endpoint',
    'request_count',
    'estimated_cost',
    'outcome',
    'safe_error_code',
    'occurred_at',
])]
class ApiUsageEvent extends Model
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
            'request_count' => 'integer',
            'estimated_cost' => 'integer',
            'outcome' => QuotaUsageOutcome::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
