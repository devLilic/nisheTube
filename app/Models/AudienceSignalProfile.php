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
 * @property int $comment_collection_run_id
 * @property string $status
 * @property string $provenance
 * @property string $provider
 * @property string $algorithm_version
 * @property string $language
 * @property int $comment_count
 * @property int $usable_comment_count
 * @property string|null $confidence_score
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'comment_collection_run_id', 'status', 'provenance', 'provider',
    'algorithm_version', 'language', 'comment_count', 'usable_comment_count', 'confidence_score',
    'warnings', 'calculated_at',
])]
class AudienceSignalProfile extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Audience Signal profiles are immutable.'));
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

    /** @return BelongsTo<CommentCollectionRun, $this> */
    public function commentCollectionRun(): BelongsTo
    {
        return $this->belongsTo(CommentCollectionRun::class);
    }

    /** @return HasMany<AudienceSignal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(AudienceSignal::class)->orderBy('kind')->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'comment_count' => 'integer',
            'usable_comment_count' => 'integer',
            'confidence_score' => 'decimal:4',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
