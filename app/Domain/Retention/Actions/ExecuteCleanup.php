<?php

namespace App\Domain\Retention\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Exports\Actions\DeleteResearchExport;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Models\AnalyzerRun;
use App\Models\ChannelSnapshot;
use App\Models\CleanupRun;
use App\Models\CollectionRun;
use App\Models\CommentCollectionRun;
use App\Models\Favorite;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\SnapshotDeletionItem;
use App\Models\Taggable;
use App\Models\TranscriptDocument;
use App\Models\VideoSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ExecuteCleanup
{
    public function __construct(private DeleteResearchExport $deleteExport) {}

    public function handle(CleanupRun $cleanup): void
    {
        $shouldRun = DB::transaction(function () use ($cleanup): bool {
            $locked = CleanupRun::query()->lockForUpdate()->find($cleanup->id);

            if (
                $locked === null
                || $locked->dry_run
                || in_array($locked->status, [CleanupStatus::Completed, CleanupStatus::Failed], true)
            ) {
                return false;
            }

            $locked->update([
                'status' => CleanupStatus::Processing,
                'started_at' => $locked->started_at ?? Date::now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

            return true;
        });

        if (! $shouldRun) {
            return;
        }

        $cleanup->items()
            ->where('outcome', DeletionOutcome::Eligible->value)
            ->orderBy('id')
            ->eachById(function (SnapshotDeletionItem $item) use ($cleanup): void {
                match ($item->target_type) {
                    CleanupTargetType::ResearchRun => $this->deleteRun($cleanup, $item),
                    CleanupTargetType::AnalyzerRun => $this->deleteAnalyzerRun($cleanup, $item),
                    CleanupTargetType::CommentCollection => $this->deleteCommentCollection($cleanup, $item),
                    CleanupTargetType::TranscriptDocument => $this->deleteTranscriptDocument($cleanup, $item),
                    CleanupTargetType::Export => $this->deleteExpiredExport($cleanup, $item),
                };
            });

        DB::transaction(function () use ($cleanup): void {
            $locked = CleanupRun::query()->lockForUpdate()->find($cleanup->id);

            if ($locked === null || $locked->status === CleanupStatus::Completed) {
                return;
            }

            $locked->update([
                'status' => CleanupStatus::Completed,
                'completed_at' => Date::now(),
            ]);
        });
    }

    private function deleteRun(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);

            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }

            $run = ResearchRun::query()
                ->where('user_id', $cleanup->user_id)
                ->where('public_id', $lockedItem->target_reference)
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedMissing]);

                return;
            }

            $favoriteExists = Favorite::query()
                ->where('user_id', $cleanup->user_id)
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->exists();

            if ($cleanup->mode !== CleanupMode::ManualSelection) {
                $terminalAt = $this->terminalAt($run);

                if ($favoriteExists) {
                    $lockedItem->update([
                        'outcome' => DeletionOutcome::PreservedFavorite,
                        'favorite_impacted' => true,
                    ]);

                    return;
                }

                if ($terminalAt === null || ! $terminalAt->lt($cleanup->cutoff_at)) {
                    $lockedItem->update(['outcome' => DeletionOutcome::SkippedIneligible]);

                    return;
                }
            }

            $sharedSourceExists = DB::table('research_run_videos as membership')
                ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
                ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
                ->where('membership.research_run_id', '!=', $run->id)
                ->where(function ($query) use ($run): void {
                    $query
                        ->where('video_source.research_run_id', $run->id)
                        ->orWhere('channel_source.research_run_id', $run->id);
                })
                ->exists();

            $sharedSourceExists = $sharedSourceExists || $this->watchlistPinsResearchRun($run);

            if ($sharedSourceExists) {
                $lockedItem->update(['outcome' => DeletionOutcome::PreservedSharedSource]);

                return;
            }

            $counts = [
                'research_runs' => 1,
                'video_snapshots' => $run->videoSnapshots()->count(),
                'channel_snapshots' => $run->channelSnapshots()->count(),
                'opportunity_scores' => $run->opportunityScores()->count(),
                'search_pages' => $run->searchPages()->count(),
                'search_results' => $run->searchResults()->count(),
                'video_memberships' => $run->videoMemberships()->count(),
            ];

            Favorite::query()
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->delete();
            Taggable::query()
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->delete();
            $run->delete();

            $lockedItem->update([
                'outcome' => DeletionOutcome::Deleted,
                'deleted_at' => Date::now(),
                'favorite_impacted' => $favoriteExists,
            ]);
            $this->addDeletedCounts($cleanup->id, $counts);
        });
    }

    private function deleteExpiredExport(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        $export = ResearchExport::query()
            ->where('user_id', $cleanup->user_id)
            ->where('public_id', $item->target_reference)
            ->first();

        if ($export === null) {
            $item->update(['outcome' => DeletionOutcome::SkippedMissing]);

            return;
        }

        if ($export->expires_at === null || ! $export->expires_at->lt(Date::now())) {
            $item->update(['outcome' => DeletionOutcome::SkippedIneligible]);

            return;
        }

        $this->deleteExport->handle($cleanup->user, $export);

        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);

            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }

            $lockedItem->update([
                'outcome' => DeletionOutcome::Deleted,
                'deleted_at' => Date::now(),
            ]);
            $this->addDeletedCounts($cleanup->id, ['expired_exports' => 1]);
        });
    }

    private function deleteAnalyzerRun(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);

            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }

            $run = AnalyzerRun::query()
                ->where('user_id', $cleanup->user_id)
                ->where('public_id', $lockedItem->target_reference)
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedMissing]);

                return;
            }

            $terminalAt = match ($run->status) {
                AnalyzerRunStatus::Completed => $run->completed_at,
                AnalyzerRunStatus::Failed => $run->failed_at,
                default => null,
            };

            if ($terminalAt === null || ! $terminalAt->lt($cleanup->cutoff_at)) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedIneligible]);

                return;
            }

            if ($this->analyzerSourceIsPinned($run)) {
                $lockedItem->update(['outcome' => DeletionOutcome::PreservedSharedSource]);

                return;
            }

            $collectionRunId = $run->collection_run_id;
            $videoSnapshots = VideoSnapshot::query()->where('collection_run_id', $collectionRunId)->count();
            $channelSnapshots = ChannelSnapshot::query()->where('collection_run_id', $collectionRunId)->count();
            $commentCollections = CommentCollectionRun::query()->where('analyzer_run_id', $run->id)->count();
            $publicComments = DB::table('public_comments')
                ->join('comment_collection_runs', 'comment_collection_runs.id', '=', 'public_comments.comment_collection_run_id')
                ->where('comment_collection_runs.analyzer_run_id', $run->id)
                ->count();
            $thumbnailProfiles = DB::table('thumbnail_analysis_profiles')
                ->where('analyzer_run_id', $run->id)
                ->count();
            $thumbnailItems = DB::table('thumbnail_analysis_items')
                ->join('thumbnail_analysis_profiles', 'thumbnail_analysis_profiles.id', '=', 'thumbnail_analysis_items.thumbnail_analysis_profile_id')
                ->where('thumbnail_analysis_profiles.analyzer_run_id', $run->id)
                ->count();
            $thumbnailAggregates = DB::table('thumbnail_performance_aggregates')
                ->join('thumbnail_analysis_profiles', 'thumbnail_analysis_profiles.id', '=', 'thumbnail_performance_aggregates.thumbnail_analysis_profile_id')
                ->where('thumbnail_analysis_profiles.analyzer_run_id', $run->id)
                ->count();
            $run->delete();
            VideoSnapshot::query()->where('collection_run_id', $collectionRunId)->delete();
            ChannelSnapshot::query()->where('collection_run_id', $collectionRunId)->delete();
            CollectionRun::query()->whereKey($collectionRunId)->delete();

            $lockedItem->update([
                'outcome' => DeletionOutcome::Deleted,
                'deleted_at' => Date::now(),
            ]);
            $this->addDeletedCounts($cleanup->id, [
                'analyzer_runs' => 1,
                'video_snapshots' => $videoSnapshots,
                'channel_snapshots' => $channelSnapshots,
                'comment_collections' => $commentCollections,
                'public_comments' => $publicComments,
                'thumbnail_analysis_profiles' => $thumbnailProfiles,
                'thumbnail_analysis_items' => $thumbnailItems,
                'thumbnail_performance_aggregates' => $thumbnailAggregates,
            ]);
        });
    }

    private function deleteCommentCollection(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);
            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }
            $run = CommentCollectionRun::query()
                ->where('user_id', $cleanup->user_id)
                ->where('public_id', $lockedItem->target_reference)
                ->lockForUpdate()
                ->first();
            if ($run === null) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedMissing]);

                return;
            }
            if ($run->collected_at === null || ! $run->collected_at->lt($cleanup->cutoff_at)) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedIneligible]);

                return;
            }

            $comments = $run->comments()->count();
            $run->delete();
            $lockedItem->update(['outcome' => DeletionOutcome::Deleted, 'deleted_at' => Date::now()]);
            $this->addDeletedCounts($cleanup->id, ['comment_collections' => 1, 'public_comments' => $comments]);
        });
    }

    private function analyzerSourceIsPinned(AnalyzerRun $run): bool
    {
        if (
            CommentCollectionRun::query()->where('analyzer_run_id', $run->id)->exists()
            || TranscriptDocument::query()->where('analyzer_run_id', $run->id)->exists()
        ) {
            return true;
        }

        $analyzerPin = DB::table('analyzer_run_videos as membership')
            ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
            ->where('membership.analyzer_run_id', '!=', $run->id)
            ->where(function ($query) use ($run): void {
                $query->where('video_source.collection_run_id', $run->collection_run_id)
                    ->orWhere('channel_source.collection_run_id', $run->collection_run_id);
            })
            ->exists();

        $directChannelPin = DB::table('analyzer_runs')
            ->join('channel_snapshots', 'channel_snapshots.id', '=', 'analyzer_runs.channel_snapshot_id')
            ->where('analyzer_runs.id', '!=', $run->id)
            ->where('channel_snapshots.collection_run_id', $run->collection_run_id)
            ->exists();

        return $analyzerPin || $directChannelPin || $this->watchlistPinsCollection($run->collection_run_id) || DB::table('research_run_videos as membership')
            ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
            ->where(function ($query) use ($run): void {
                $query->where('video_source.collection_run_id', $run->collection_run_id)
                    ->orWhere('channel_source.collection_run_id', $run->collection_run_id);
            })
            ->exists();
    }

    private function deleteTranscriptDocument(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);
            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }
            $document = TranscriptDocument::query()
                ->where('user_id', $cleanup->user_id)
                ->where('public_id', $lockedItem->target_reference)
                ->lockForUpdate()
                ->first();
            if ($document === null) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedMissing]);

                return;
            }
            if (! $document->provided_at->lt($cleanup->cutoff_at)) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedIneligible]);

                return;
            }

            $segments = $document->segments()->count();
            $document->delete();
            $lockedItem->update(['outcome' => DeletionOutcome::Deleted, 'deleted_at' => Date::now()]);
            $this->addDeletedCounts($cleanup->id, [
                'transcript_documents' => 1,
                'transcript_segments' => $segments,
            ]);
        });
    }

    private function watchlistPinsResearchRun(ResearchRun $run): bool
    {
        return DB::table('watchlist_refresh_runs as refreshes')
            ->leftJoin('video_snapshots as previous_video', 'previous_video.id', '=', 'refreshes.previous_video_snapshot_id')
            ->leftJoin('video_snapshots as current_video', 'current_video.id', '=', 'refreshes.current_video_snapshot_id')
            ->leftJoin('channel_snapshots as previous_channel', 'previous_channel.id', '=', 'refreshes.previous_channel_snapshot_id')
            ->leftJoin('channel_snapshots as current_channel', 'current_channel.id', '=', 'refreshes.current_channel_snapshot_id')
            ->where('refreshes.user_id', $run->user_id)
            ->where(function ($query) use ($run): void {
                $query->where('previous_video.research_run_id', $run->id)
                    ->orWhere('current_video.research_run_id', $run->id)
                    ->orWhere('previous_channel.research_run_id', $run->id)
                    ->orWhere('current_channel.research_run_id', $run->id);
            })->exists();
    }

    private function watchlistPinsCollection(int $collectionRunId): bool
    {
        if (DB::table('watchlist_refresh_runs')->where('collection_run_id', $collectionRunId)->exists()) {
            return true;
        }

        return DB::table('watchlist_refresh_runs as refreshes')
            ->leftJoin('video_snapshots as previous_video', 'previous_video.id', '=', 'refreshes.previous_video_snapshot_id')
            ->leftJoin('video_snapshots as current_video', 'current_video.id', '=', 'refreshes.current_video_snapshot_id')
            ->leftJoin('channel_snapshots as previous_channel', 'previous_channel.id', '=', 'refreshes.previous_channel_snapshot_id')
            ->leftJoin('channel_snapshots as current_channel', 'current_channel.id', '=', 'refreshes.current_channel_snapshot_id')
            ->where(function ($query) use ($collectionRunId): void {
                $query->where('previous_video.collection_run_id', $collectionRunId)
                    ->orWhere('current_video.collection_run_id', $collectionRunId)
                    ->orWhere('previous_channel.collection_run_id', $collectionRunId)
                    ->orWhere('current_channel.collection_run_id', $collectionRunId);
            })->exists();
    }

    /** @param array<string, int> $increments */
    private function addDeletedCounts(int $cleanupId, array $increments): void
    {
        $cleanup = CleanupRun::query()->lockForUpdate()->findOrFail($cleanupId);
        $counts = $cleanup->deleted_counts;

        foreach ($increments as $key => $increment) {
            $counts[$key] = ($counts[$key] ?? 0) + $increment;
        }

        $cleanup->update(['deleted_counts' => $counts]);
    }

    private function terminalAt(ResearchRun $run): ?CarbonInterface
    {
        return match ($run->status) {
            ResearchRunStatus::Completed => $run->completed_at,
            ResearchRunStatus::Failed => $run->failed_at,
            default => null,
        };
    }
}
