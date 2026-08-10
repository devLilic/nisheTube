<?php

namespace Tests\Feature\Retention;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Models\Channel;
use App\Models\CommentCollectionRun;
use App\Models\PublicComment;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CommentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_comment_text_is_previewed_audited_and_deleted_without_deleting_preserved_analyzer_data(): void
    {
        Queue::fake();
        Date::setTestNow('2026-08-10 12:00:00 UTC');
        $user = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handle($user, 'dQw4w9WgXcQ', CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create(['provider' => 'youtube', 'provider_channel_id' => 'channel-1', 'title' => 'Channel']);
        $video = Video::query()->create([
            'provider' => 'youtube', 'provider_video_id' => 'dQw4w9WgXcQ', 'channel_id' => $channel->id,
            'title' => 'Video', 'published_at' => now()->subYear(),
        ]);
        DB::table('analyzer_runs')->where('id', $run->id)->update([
            'video_id' => $video->id, 'channel_id' => $channel->id, 'status' => AnalyzerRunStatus::Completed->value,
            'progress_percent' => 100,
            'completed_at' => now()->subMonthsNoOverflow(6)->subDay(),
            'calculated_at' => now()->subMonthsNoOverflow(6)->subDay(),
        ]);
        $comments = CommentCollectionRun::query()->create([
            'user_id' => $user->id, 'analyzer_run_id' => $run->id, 'video_id' => $video->id,
            'provider' => 'youtube', 'provider_video_id' => 'dQw4w9WgXcQ', 'status' => CommentCollectionStatus::Completed,
            'max_comments' => 100, 'page_size' => 100, 'pages_collected' => 1, 'comments_collected' => 1,
            'reply_scope' => 'top_level_only', 'collected_at' => now()->subMonthsNoOverflow(6)->subSecond(),
            'completed_at' => now()->subMonthsNoOverflow(6)->subSecond(),
        ]);
        PublicComment::query()->create([
            'comment_collection_run_id' => $comments->id, 'provider_comment_id' => 'old-comment',
            'text' => 'Retained only for the configured window.', 'like_count' => 2, 'reply_count' => 1,
        ]);

        $plan = app(BuildRetentionPlan::class)->handle($user);
        $this->assertSame(1, $plan->counts['comment_collections']);
        $this->assertSame(1, $plan->counts['public_comments']);
        $this->assertSame(0, $plan->counts['analyzer_runs']);

        $cleanup = app(CreateCleanupRun::class)->handle($user, CleanupMode::ManualRetention, false, initiator: $user);
        app(ExecuteCleanup::class)->handle($cleanup);

        $this->assertDatabaseMissing('comment_collection_runs', ['id' => $comments->id]);
        $this->assertDatabaseMissing('public_comments', ['provider_comment_id' => 'old-comment']);
        $this->assertDatabaseHas('analyzer_runs', ['id' => $run->id]);
        $this->assertSame(1, $cleanup->fresh()->deleted_counts['comment_collections']);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'target_type' => 'comment_collection', 'target_reference' => $comments->public_id, 'outcome' => 'deleted',
        ]);
    }
}
