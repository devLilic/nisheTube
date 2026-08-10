<?php

namespace App\Models;

use App\Domain\Collection\Enums\CollectionRunStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

/**
 * @property int $research_run_id
 * @property int $video_id
 * @property int|null $video_snapshot_id
 * @property int|null $channel_snapshot_id
 * @property int $result_rank
 * @property int $page_number
 * @property int $provider_order
 * @property array<string, mixed>|null $matched_query_metadata
 */
#[Fillable([
    'research_run_id',
    'video_id',
    'video_snapshot_id',
    'channel_snapshot_id',
    'result_rank',
    'page_number',
    'provider_order',
    'matched_query_metadata',
])]
class ResearchRunVideo extends Pivot
{
    public $incrementing = false;

    protected $table = 'research_run_videos';

    protected static function booted(): void
    {
        static::updating(function (self $source): void {
            foreach (['video_snapshot_id', 'channel_snapshot_id'] as $attribute) {
                $original = $source->getRawOriginal($attribute);

                if ($original !== null && $source->isDirty($attribute)) {
                    throw new DomainException('Pinned research observation sources are immutable.');
                }
            }
        });
    }

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

    /** @return BelongsTo<VideoSnapshot, $this> */
    public function videoSnapshot(): BelongsTo
    {
        return $this->belongsTo(VideoSnapshot::class);
    }

    /** @return BelongsTo<ChannelSnapshot, $this> */
    public function channelSnapshot(): BelongsTo
    {
        return $this->belongsTo(ChannelSnapshot::class);
    }

    public function pinSources(VideoSnapshot $videoSnapshot, ?ChannelSnapshot $channelSnapshot): void
    {
        $researchRun = $this->researchRun()->firstOrFail();
        $targetCollectionRun = $researchRun->collectionRun()->firstOrFail();
        $videoSourceRun = $videoSnapshot->collectionRun()->first();
        $channelSourceRun = $channelSnapshot?->collectionRun()->first();

        if (
            (int) $videoSnapshot->video_id !== (int) $this->video_id
            || $videoSourceRun === null
            || $videoSourceRun->user_id !== $researchRun->user_id
            || $videoSourceRun->provider !== $targetCollectionRun->provider
            || (
                $videoSourceRun->id !== $researchRun->collection_run_id
                && $videoSourceRun->status !== CollectionRunStatus::Completed
            )
            || ($channelSnapshot !== null && $channelSourceRun === null)
            || ($channelSourceRun !== null && $channelSourceRun->user_id !== $researchRun->user_id)
            || (
                $channelSourceRun !== null
                && $channelSourceRun->id !== $videoSourceRun->id
            )
            || (
                $channelSnapshot !== null
                && $channelSnapshot->channel_id !== (int) DB::table('videos')
                    ->where('id', $this->video_id)
                    ->value('channel_id')
            )
        ) {
            throw new DomainException('Observation sources must be compatible owner-scoped collection inputs.');
        }

        if ($this->video_snapshot_id !== null && $this->video_snapshot_id !== $videoSnapshot->id) {
            throw new DomainException('Pinned research observation sources are immutable.');
        }

        if (
            $channelSnapshot !== null
            && $this->channel_snapshot_id !== null
            && $this->channel_snapshot_id !== $channelSnapshot->id
        ) {
            throw new DomainException('Pinned research observation sources are immutable.');
        }

        $sources = [
            'video_snapshot_id' => $videoSnapshot->id,
            'channel_snapshot_id' => $channelSnapshot === null
                ? $this->channel_snapshot_id
                : $channelSnapshot->id,
        ];

        DB::table($this->getTable())
            ->where('research_run_id', $this->research_run_id)
            ->where('video_id', $this->video_id)
            ->update($sources);

        $this->forceFill($sources)->syncOriginalAttributes(array_keys($sources));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'result_rank' => 'integer',
            'page_number' => 'integer',
            'provider_order' => 'integer',
            'video_snapshot_id' => 'integer',
            'channel_snapshot_id' => 'integer',
            'matched_query_metadata' => 'array',
        ];
    }
}
