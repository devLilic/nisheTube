<?php

namespace App\Domain\Retention\Services;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Data\RetentionPlan;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Models\AnalyzerRun;
use App\Models\CommentCollectionRun;
use App\Models\Favorite;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\TranscriptDocument;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class BuildRetentionPlan
{
    /**
     * @param  list<string>  $selectedRunPublicIds
     *
     * @throws AuthorizationException
     */
    public function handle(
        User $user,
        CleanupMode $mode = CleanupMode::ManualRetention,
        array $selectedRunPublicIds = [],
    ): RetentionPlan {
        $months = max(1, (int) config('retention.months', 6));
        $cutoff = Date::now()->subMonthsNoOverflow($months)->toImmutable();
        $counts = $this->emptyCounts();
        $items = [];
        $runRows = [];

        $runs = $mode === CleanupMode::ManualSelection
            ? $this->selectedRuns($user, $selectedRunPublicIds)
            : $this->retentionRuns($user, $cutoff);

        $favoriteRunIds = Favorite::query()
            ->where('user_id', $user->id)
            ->where('target_type', LibraryTargetType::ResearchRun->value)
            ->whereIn('target_id', $runs->pluck('id'))
            ->pluck('target_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        foreach ($runs as $run) {
            $favoriteImpacted = $favoriteRunIds->has($run->id);
            $sharedSourceImpacted = $this->hasExternalSourcePins($run);
            $watchlistImpacted = $this->watchlistPinsResearchRun($run);
            $sharedSourceImpacted = $sharedSourceImpacted || $watchlistImpacted;
            $preservedFavorite = $mode !== CleanupMode::ManualSelection && $favoriteImpacted;
            $preserved = $preservedFavorite || $sharedSourceImpacted;
            $terminalAt = $this->terminalAt($run);

            if ($terminalAt === null) {
                continue;
            }

            $items[] = [
                'target_type' => CleanupTargetType::ResearchRun,
                'target_reference' => $run->public_id,
                'original_collection_at' => $terminalAt,
                'outcome' => match (true) {
                    $sharedSourceImpacted => DeletionOutcome::PreservedSharedSource,
                    $preservedFavorite => DeletionOutcome::PreservedFavorite,
                    default => DeletionOutcome::Eligible,
                },
                'favorite_impacted' => $favoriteImpacted,
            ];
            $runRows[] = [
                'public_id' => $run->public_id,
                'query_text' => $run->query_text,
                'status' => $run->status->value,
                'terminal_at' => $terminalAt->toIso8601String(),
                'favorite_impacted' => $favoriteImpacted,
                'shared_source_impacted' => $sharedSourceImpacted,
                'video_snapshots' => $run->video_snapshots_count,
                'channel_snapshots' => $run->channel_snapshots_count,
                'opportunity_scores' => $run->opportunity_scores_count,
                'artifacts' => $run->video_snapshots_count
                    + $run->channel_snapshots_count
                    + $run->opportunity_scores_count
                    + $run->search_pages_count
                    + $run->search_results_count
                    + $run->video_memberships_count,
            ];

            if ($preserved) {
                if ($watchlistImpacted) {
                    $counts['watchlist_source_links']++;
                }
                if ($sharedSourceImpacted) {
                    $counts['preserved_shared_sources']++;
                } else {
                    $counts['preserved_favorites']++;
                }

                continue;
            }

            $counts['research_runs']++;
            $counts['video_snapshots'] += $run->video_snapshots_count;
            $counts['channel_snapshots'] += $run->channel_snapshots_count;
            $counts['opportunity_scores'] += $run->opportunity_scores_count;
            $counts['search_pages'] += $run->search_pages_count;
            $counts['search_results'] += $run->search_results_count;
            $counts['video_memberships'] += $run->video_memberships_count;
        }

        if ($mode !== CleanupMode::ManualSelection) {
            foreach ($this->retentionAnalyzerRuns($user, $cutoff) as $analyzerRun) {
                $sharedSourceImpacted = $this->hasExternalAnalyzerSourcePins($analyzerRun);
                $watchlistImpacted = $this->watchlistPinsCollection($analyzerRun->collection_run_id);
                $sharedSourceImpacted = $sharedSourceImpacted || $watchlistImpacted;
                $terminalAt = $analyzerRun->completed_at ?? $analyzerRun->failed_at;

                if ($terminalAt === null) {
                    continue;
                }

                $videoSnapshotCount = DB::table('video_snapshots')
                    ->where('collection_run_id', $analyzerRun->collection_run_id)
                    ->count();
                $channelSnapshotCount = DB::table('channel_snapshots')
                    ->where('collection_run_id', $analyzerRun->collection_run_id)
                    ->count();
                $commentCollectionCount = CommentCollectionRun::query()
                    ->where('analyzer_run_id', $analyzerRun->id)
                    ->count();
                $publicCommentCount = DB::table('public_comments')
                    ->join('comment_collection_runs', 'comment_collection_runs.id', '=', 'public_comments.comment_collection_run_id')
                    ->where('comment_collection_runs.analyzer_run_id', $analyzerRun->id)
                    ->count();
                $thumbnailProfileCount = DB::table('thumbnail_analysis_profiles')
                    ->where('analyzer_run_id', $analyzerRun->id)
                    ->count();
                $thumbnailItemCount = DB::table('thumbnail_analysis_items')
                    ->join('thumbnail_analysis_profiles', 'thumbnail_analysis_profiles.id', '=', 'thumbnail_analysis_items.thumbnail_analysis_profile_id')
                    ->where('thumbnail_analysis_profiles.analyzer_run_id', $analyzerRun->id)
                    ->count();
                $thumbnailAggregateCount = DB::table('thumbnail_performance_aggregates')
                    ->join('thumbnail_analysis_profiles', 'thumbnail_analysis_profiles.id', '=', 'thumbnail_performance_aggregates.thumbnail_analysis_profile_id')
                    ->where('thumbnail_analysis_profiles.analyzer_run_id', $analyzerRun->id)
                    ->count();
                $items[] = [
                    'target_type' => CleanupTargetType::AnalyzerRun,
                    'target_reference' => $analyzerRun->public_id,
                    'original_collection_at' => $terminalAt,
                    'outcome' => $sharedSourceImpacted
                        ? DeletionOutcome::PreservedSharedSource
                        : DeletionOutcome::Eligible,
                    'favorite_impacted' => false,
                ];

                if ($sharedSourceImpacted) {
                    $counts['preserved_shared_sources']++;
                    if ($watchlistImpacted) {
                        $counts['watchlist_source_links']++;
                    }

                    continue;
                }

                $counts['analyzer_runs']++;
                $counts['video_snapshots'] += $videoSnapshotCount;
                $counts['channel_snapshots'] += $channelSnapshotCount;
                $counts['comment_collections'] += $commentCollectionCount;
                $counts['public_comments'] += $publicCommentCount;
                $counts['thumbnail_analysis_profiles'] += $thumbnailProfileCount;
                $counts['thumbnail_analysis_items'] += $thumbnailItemCount;
                $counts['thumbnail_performance_aggregates'] += $thumbnailAggregateCount;
            }
        }

        if ($mode !== CleanupMode::ManualSelection) {
            $deletingAnalyzerReferences = collect($items)
                ->filter(fn (array $item): bool => $item['target_type'] === CleanupTargetType::AnalyzerRun
                    && $item['outcome'] === DeletionOutcome::Eligible)
                ->pluck('target_reference');
            $commentRuns = CommentCollectionRun::query()
                ->where('user_id', $user->id)
                ->whereNotNull('collected_at')
                ->where('collected_at', '<', $cutoff)
                ->when($deletingAnalyzerReferences->isNotEmpty(), fn (Builder $query) => $query
                    ->whereHas('analyzerRun', fn (Builder $analyzer) => $analyzer
                        ->whereNotIn('public_id', $deletingAnalyzerReferences)))
                ->withCount('comments')
                ->orderBy('id')
                ->get();

            foreach ($commentRuns as $commentRun) {
                $items[] = [
                    'target_type' => CleanupTargetType::CommentCollection,
                    'target_reference' => $commentRun->public_id,
                    'original_collection_at' => $commentRun->collected_at,
                    'outcome' => DeletionOutcome::Eligible,
                    'favorite_impacted' => false,
                ];
                $counts['comment_collections']++;
                $counts['public_comments'] += $commentRun->comments_count;
            }

            $transcripts = TranscriptDocument::query()
                ->where('user_id', $user->id)
                ->where('provided_at', '<', $cutoff)
                ->withCount('segments')
                ->orderBy('id')
                ->get();

            foreach ($transcripts as $transcript) {
                $items[] = [
                    'target_type' => CleanupTargetType::TranscriptDocument,
                    'target_reference' => $transcript->public_id,
                    'original_collection_at' => $transcript->provided_at,
                    'outcome' => DeletionOutcome::Eligible,
                    'favorite_impacted' => false,
                ];
                $counts['transcript_documents']++;
                $counts['transcript_segments'] += $transcript->segments_count;
            }
        }

        if ($mode !== CleanupMode::ManualSelection) {
            foreach ($this->expiredExports($user) as $export) {
                $collectedAt = $export->completed_at ?? $export->created_at;

                if ($collectedAt === null) {
                    continue;
                }

                $items[] = [
                    'target_type' => CleanupTargetType::Export,
                    'target_reference' => $export->public_id,
                    'original_collection_at' => $collectedAt,
                    'outcome' => DeletionOutcome::Eligible,
                    'favorite_impacted' => false,
                ];
                $counts['expired_exports']++;
            }
        }

        return new RetentionPlan($cutoff, $counts, $items, $runRows);
    }

    /** @return Builder<ResearchRun> */
    private function runQuery(User $user): Builder
    {
        return ResearchRun::query()
            ->where('user_id', $user->id)
            ->withCount([
                'videoSnapshots',
                'channelSnapshots',
                'opportunityScores',
                'searchPages',
                'searchResults',
                'videoMemberships',
            ]);
    }

    /** @return Collection<int, ResearchRun> */
    private function retentionRuns(User $user, CarbonInterface $cutoff): Collection
    {
        return $this->runQuery($user)
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $completed) use ($cutoff): void {
                        $completed
                            ->where('status', ResearchRunStatus::Completed->value)
                            ->where('completed_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $failed) use ($cutoff): void {
                        $failed
                            ->where('status', ResearchRunStatus::Failed->value)
                            ->where('failed_at', '<', $cutoff);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $publicIds
     * @return Collection<int, ResearchRun>
     *
     * @throws AuthorizationException
     */
    private function selectedRuns(User $user, array $publicIds): Collection
    {
        $uniqueIds = array_values(array_unique($publicIds));

        if ($uniqueIds === []) {
            throw new AuthorizationException('No owned research runs were selected.');
        }

        $runs = $this->runQuery($user)
            ->whereIn('public_id', $uniqueIds)
            ->whereIn('status', [ResearchRunStatus::Completed->value, ResearchRunStatus::Failed->value])
            ->orderBy('id')
            ->get();

        if ($runs->count() !== count($uniqueIds)) {
            throw new AuthorizationException('One or more research runs are unavailable.');
        }

        return $runs;
    }

    /** @return Collection<int, ResearchExport> */
    private function expiredExports(User $user): Collection
    {
        return ResearchExport::query()
            ->where('user_id', $user->id)
            ->where('status', ExportStatus::Completed->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Date::now())
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, AnalyzerRun> */
    private function retentionAnalyzerRuns(User $user, CarbonInterface $cutoff): Collection
    {
        return AnalyzerRun::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $completed) use ($cutoff): void {
                        $completed->where('status', AnalyzerRunStatus::Completed->value)
                            ->where('completed_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $failed) use ($cutoff): void {
                        $failed->where('status', AnalyzerRunStatus::Failed->value)
                            ->where('failed_at', '<', $cutoff);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function terminalAt(ResearchRun $run): ?CarbonInterface
    {
        return match ($run->status) {
            ResearchRunStatus::Completed => $run->completed_at,
            ResearchRunStatus::Failed => $run->failed_at,
            default => null,
        };
    }

    private function hasExternalSourcePins(ResearchRun $run): bool
    {
        return DB::table('research_run_videos as membership')
            ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
            ->where('membership.research_run_id', '!=', $run->id)
            ->where(function (QueryBuilder $query) use ($run): void {
                $query
                    ->where('video_source.research_run_id', $run->id)
                    ->orWhere('channel_source.research_run_id', $run->id);
            })
            ->exists();
    }

    private function hasExternalAnalyzerSourcePins(AnalyzerRun $run): bool
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
            ->where(function (QueryBuilder $query) use ($run): void {
                $query->where('video_source.collection_run_id', $run->collection_run_id)
                    ->orWhere('channel_source.collection_run_id', $run->collection_run_id);
            })
            ->exists();

        if ($analyzerPin) {
            return true;
        }

        $directChannelPin = DB::table('analyzer_runs')
            ->join('channel_snapshots', 'channel_snapshots.id', '=', 'analyzer_runs.channel_snapshot_id')
            ->where('analyzer_runs.id', '!=', $run->id)
            ->where('channel_snapshots.collection_run_id', $run->collection_run_id)
            ->exists();

        if ($directChannelPin) {
            return true;
        }

        return DB::table('research_run_videos as membership')
            ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
            ->where(function (QueryBuilder $query) use ($run): void {
                $query->where('video_source.collection_run_id', $run->collection_run_id)
                    ->orWhere('channel_source.collection_run_id', $run->collection_run_id);
            })
            ->exists();
    }

    private function watchlistPinsResearchRun(ResearchRun $run): bool
    {
        return DB::table('watchlist_refresh_runs as refreshes')
            ->leftJoin('video_snapshots as previous_video', 'previous_video.id', '=', 'refreshes.previous_video_snapshot_id')
            ->leftJoin('video_snapshots as current_video', 'current_video.id', '=', 'refreshes.current_video_snapshot_id')
            ->leftJoin('channel_snapshots as previous_channel', 'previous_channel.id', '=', 'refreshes.previous_channel_snapshot_id')
            ->leftJoin('channel_snapshots as current_channel', 'current_channel.id', '=', 'refreshes.current_channel_snapshot_id')
            ->where('refreshes.user_id', $run->user_id)
            ->where(function (QueryBuilder $query) use ($run): void {
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
            ->where(function (QueryBuilder $query) use ($collectionRunId): void {
                $query->where('previous_video.collection_run_id', $collectionRunId)
                    ->orWhere('current_video.collection_run_id', $collectionRunId)
                    ->orWhere('previous_channel.collection_run_id', $collectionRunId)
                    ->orWhere('current_channel.collection_run_id', $collectionRunId);
            })->exists();
    }

    /** @return array<string, int> */
    private function emptyCounts(): array
    {
        return [
            'research_runs' => 0,
            'analyzer_runs' => 0,
            'comment_collections' => 0,
            'public_comments' => 0,
            'transcript_documents' => 0,
            'transcript_segments' => 0,
            'thumbnail_analysis_profiles' => 0,
            'thumbnail_analysis_items' => 0,
            'thumbnail_performance_aggregates' => 0,
            'video_snapshots' => 0,
            'channel_snapshots' => 0,
            'opportunity_scores' => 0,
            'search_pages' => 0,
            'search_results' => 0,
            'video_memberships' => 0,
            'expired_exports' => 0,
            'preserved_favorites' => 0,
            'preserved_shared_sources' => 0,
            'watchlist_source_links' => 0,
        ];
    }
}
