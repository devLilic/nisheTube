<?php

namespace Tests\Feature\Retention;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Models\Channel;
use App\Models\TranscriptDocument;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TranscriptRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_transcript_is_previewed_audited_and_deleted_without_deleting_analyzer_data(): void
    {
        Queue::fake();
        Date::setTestNow('2026-08-10 12:00:00 UTC');
        $user = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handle($user, 'dQw4w9WgXcQ', CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create([
            'provider' => 'youtube', 'provider_channel_id' => 'transcript-retention-channel', 'title' => 'Channel',
        ]);
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
        $document = TranscriptDocument::query()->create([
            'user_id' => $user->id, 'analyzer_run_id' => $run->id, 'video_id' => $video->id,
            'provider' => 'user_provided', 'provider_version' => 'user-provided-transcript-v1',
            'status' => 'available', 'input_format' => 'timestamped_text', 'language' => 'en',
            'source_text' => '[0:00] Retention evidence', 'plain_text' => 'Retention evidence',
            'character_count' => 18, 'segment_count' => 1, 'checksum_sha256' => hash('sha256', 'retention'),
            'rights_confirmed_at' => now()->subMonthsNoOverflow(6)->subSecond(),
            'provided_at' => now()->subMonthsNoOverflow(6)->subSecond(),
        ]);
        $document->segments()->create(['position' => 1, 'start_ms' => 0, 'text' => 'Retention evidence']);

        $plan = app(BuildRetentionPlan::class)->handle($user);
        $this->assertSame(1, $plan->counts['transcript_documents']);
        $this->assertSame(1, $plan->counts['transcript_segments']);
        $this->assertSame(0, $plan->counts['analyzer_runs']);

        $cleanup = app(CreateCleanupRun::class)->handle($user, CleanupMode::ManualRetention, false, initiator: $user);
        app(ExecuteCleanup::class)->handle($cleanup);

        $this->assertDatabaseMissing('transcript_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('transcript_segments', ['transcript_document_id' => $document->id]);
        $this->assertDatabaseHas('analyzer_runs', ['id' => $run->id]);
        $this->assertSame(1, $cleanup->fresh()->deleted_counts['transcript_documents']);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'target_type' => 'transcript_document', 'target_reference' => $document->public_id, 'outcome' => 'deleted',
        ]);
    }
}
