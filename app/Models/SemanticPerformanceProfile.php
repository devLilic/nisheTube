<?php

namespace App\Models;

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
 * @property int|null $semantic_topic_profile_id
 * @property string $status
 * @property string $provenance
 * @property string $calculation_version
 * @property string|null $topic_version
 * @property string $title_pattern_version
 * @property int $minimum_sample_size
 * @property int $cohort_video_count
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 * @property AnalyzerRun $analyzerRun
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'semantic_topic_profile_id', 'status', 'provenance', 'calculation_version',
    'topic_version', 'title_pattern_version', 'minimum_sample_size', 'cohort_video_count', 'warnings', 'calculated_at',
])]
class SemanticPerformanceProfile extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Semantic performance profiles are immutable.'));
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

    /** @return BelongsTo<SemanticTopicProfile, $this> */
    public function topicProfile(): BelongsTo
    {
        return $this->belongsTo(SemanticTopicProfile::class, 'semantic_topic_profile_id');
    }

    /** @return HasMany<SemanticPerformanceAggregate, $this> */
    public function aggregates(): HasMany
    {
        return $this->hasMany(SemanticPerformanceAggregate::class)->orderBy('group_type')->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'minimum_sample_size' => 'integer',
            'cohort_video_count' => 'integer',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
