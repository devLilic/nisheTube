<?php

namespace App\Domain\Comments\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Jobs\Comments\CollectPublicComments;
use App\Models\AnalyzerRun;
use App\Models\CommentCollectionRun;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class StartCommentCollection
{
    public function handle(User $user, AnalyzerRun $analyzerRun): CommentCollectionRun
    {
        if ($analyzerRun->user_id !== $user->id || $analyzerRun->status !== AnalyzerRunStatus::Completed) {
            throw new DomainException('Comments can only be collected for an owned completed Analyzer result.');
        }

        $membership = $analyzerRun->videoMemberships()->where('role', 'anchor')->first();
        if ($analyzerRun->target_kind !== 'video' || $membership === null) {
            throw new DomainException('Public comments are available only for a completed video analysis.');
        }

        $run = DB::transaction(function () use ($user, $analyzerRun, $membership): CommentCollectionRun {
            AnalyzerRun::query()->whereKey($analyzerRun->id)->lockForUpdate()->firstOrFail();
            $active = CommentCollectionRun::query()
                ->where('user_id', $user->id)
                ->where('analyzer_run_id', $analyzerRun->id)
                ->whereIn('status', [CommentCollectionStatus::Queued->value, CommentCollectionStatus::Collecting->value])
                ->first();
            if ($active !== null) {
                return $active;
            }

            return CommentCollectionRun::query()->create([
                'user_id' => $user->id,
                'analyzer_run_id' => $analyzerRun->id,
                'video_id' => $membership->video_id,
                'provider' => 'youtube',
                'provider_video_id' => $analyzerRun->target_provider_id,
                'status' => CommentCollectionStatus::Queued,
                'max_comments' => max(1, min(1000, (int) config('comments.max_comments', 200))),
                'page_size' => max(1, min(100, (int) config('comments.page_size', 100))),
                'reply_scope' => 'top_level_only',
            ]);
        });

        if ($run->wasRecentlyCreated) {
            CollectPublicComments::dispatch($run->id);
        }

        return $run;
    }
}
