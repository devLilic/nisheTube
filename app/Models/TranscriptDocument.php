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
 * @property int $video_id
 * @property string $provider
 * @property string $provider_version
 * @property string $status
 * @property string $input_format
 * @property string $language
 * @property string $source_text
 * @property string $plain_text
 * @property int $character_count
 * @property int $segment_count
 * @property int $segments_count
 * @property string $checksum_sha256
 * @property list<string>|null $warnings
 * @property Carbon $rights_confirmed_at
 * @property Carbon $provided_at
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'video_id', 'provider', 'provider_version', 'status', 'input_format',
    'language', 'source_text', 'plain_text', 'character_count', 'segment_count', 'checksum_sha256',
    'warnings', 'rights_confirmed_at', 'provided_at',
])]
class TranscriptDocument extends Model
{
    use HasPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Transcript documents are immutable. Paste a new revision instead.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return HasMany<TranscriptSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('position');
    }

    /** @return HasMany<TranscriptStructureProfile, $this> */
    public function structureProfiles(): HasMany
    {
        return $this->hasMany(TranscriptStructureProfile::class);
    }

    protected function casts(): array
    {
        return [
            'character_count' => 'integer',
            'segment_count' => 'integer',
            'warnings' => 'array',
            'rights_confirmed_at' => 'immutable_datetime',
            'provided_at' => 'immutable_datetime',
        ];
    }
}
