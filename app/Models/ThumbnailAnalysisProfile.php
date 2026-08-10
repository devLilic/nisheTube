<?php

namespace App\Models;

use App\Domain\Thumbnails\Enums\ThumbnailAnalysisStatus;
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
 * @property int $analyzer_run_id
 * @property ThumbnailAnalysisStatus $status
 * @property string $provenance
 * @property string $provider
 * @property string $algorithm_version
 * @property string $calculation_version
 * @property int $attempt_number
 * @property int $minimum_sample_size
 * @property int $cohort_video_count
 * @property int $processed_image_count
 * @property int $available_image_count
 * @property int $reused_image_count
 * @property int $unavailable_image_count
 * @property string|null $confidence_score
 * @property list<string>|null $warnings
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $calculated_at
 * @property Carbon|null $failed_at
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'status', 'provenance', 'provider', 'algorithm_version', 'calculation_version',
    'attempt_number', 'minimum_sample_size', 'cohort_video_count', 'processed_image_count', 'available_image_count',
    'reused_image_count', 'unavailable_image_count', 'confidence_score', 'warnings', 'error_code', 'error_message',
    'started_at', 'calculated_at', 'failed_at',
])]
class ThumbnailAnalysisProfile extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(function (self $profile): void {
            $original = ThumbnailAnalysisStatus::tryFrom((string) $profile->getRawOriginal('status'));
            if ($original?->isTerminal()) {
                throw new DomainException('Completed thumbnail analysis profiles are immutable.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    /** @return HasMany<ThumbnailAnalysisItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ThumbnailAnalysisItem::class)->orderBy('id');
    }

    /** @return HasMany<ThumbnailPerformanceAggregate, $this> */
    public function aggregates(): HasMany
    {
        return $this->hasMany(ThumbnailPerformanceAggregate::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'status' => ThumbnailAnalysisStatus::class,
            'attempt_number' => 'integer',
            'minimum_sample_size' => 'integer',
            'cohort_video_count' => 'integer',
            'processed_image_count' => 'integer',
            'available_image_count' => 'integer',
            'reused_image_count' => 'integer',
            'unavailable_image_count' => 'integer',
            'confidence_score' => 'decimal:4',
            'warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
