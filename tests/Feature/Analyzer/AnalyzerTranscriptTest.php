<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAnalysisMetric;
use App\Models\VideoSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_paste_timestamped_optional_transcript_and_duplicate_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $payload = [
            'transcript' => "[0:00] First dumpling segment\n[0:09] Second dumpling segment",
            'language' => 'en',
            'rights_confirmed' => true,
        ];

        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), $payload)->assertRedirect();

        $this->assertDatabaseCount('transcript_documents', 1);
        $this->assertDatabaseCount('transcript_segments', 2);
        $this->assertDatabaseHas('transcript_documents', [
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'provider_version' => 'user-provided-transcript-v1',
            'input_format' => 'timestamped_text',
            'status' => 'available',
        ]);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.transcript.status', 'available')
            ->where('run.transcript.document.segment_count', 2)
            ->where('run.transcript.document.segments.0.start_ms', 0)
            ->where('run.transcript.document.segments.0.end_ms', 9000)
            ->where('run.transcript.document.segments.1.text', 'Second dumpling segment')
            ->where('run.transcript.document.revision_count', 1)
            ->has('run.transcript.document.retention_cutoff_at'));
    }

    public function test_transcript_is_optional_owner_scoped_and_requires_rights_confirmation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $payload = ['transcript' => 'Optional text', 'language' => 'en', 'rights_confirmed' => true];

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.status', 'completed')
            ->where('run.transcript.status', 'not_provided')
            ->where('run.transcript.document', null)
            ->where('run.metrics.calculation_version', 'fixture-metrics-v1'));
        $this->app['auth']->logout();
        $this->post(route('analyzer.runs.transcripts.store', $run), $payload)->assertRedirect(route('login'));
        $this->actingAs($other)->post(route('analyzer.runs.transcripts.store', $run), $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), [
            ...$payload,
            'rights_confirmed' => false,
        ])->assertSessionHasErrors('rights_confirmed');
        $this->assertDatabaseCount('transcript_documents', 0);
    }

    public function test_plain_revision_delete_is_confirmable_and_does_not_change_analyzer_metrics(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $metricsBefore = $run->videoMetrics()->firstOrFail()->only(['age_seconds', 'calculation_version']);
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), [
            'transcript' => 'Plain transcript evidence.', 'language' => 'en', 'rights_confirmed' => true,
        ])->assertRedirect();
        $document = $run->transcriptDocuments()->firstOrFail();

        $this->actingAs($owner)->delete(route('analyzer.runs.transcripts.destroy', [$run, $document]))->assertRedirect();

        $this->assertDatabaseCount('transcript_documents', 0);
        $this->assertDatabaseCount('transcript_segments', 0);
        $this->assertDatabaseHas('transcript_deletion_audits', [
            'user_id' => $owner->id,
            'analyzer_run_public_id' => $run->public_id,
            'transcript_document_public_id' => $document->public_id,
            'provider_video_id' => $run->target_provider_id,
            'character_count' => 26,
            'segment_count' => 1,
        ]);
        $this->assertSame($metricsBefore, $run->videoMetrics()->firstOrFail()->only(['age_seconds', 'calculation_version']));
        $this->assertSame(AnalyzerRunStatus::Completed, $run->fresh()->status);
    }

    public function test_foreign_or_mismatched_transcript_deletion_does_not_leak_or_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $secondRun = $this->completedVideoRun($owner, '9bZkp7q19f0', 'channel-second');
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), [
            'transcript' => 'Owned transcript', 'language' => 'en', 'rights_confirmed' => true,
        ]);
        $document = $run->transcriptDocuments()->firstOrFail();

        $this->actingAs($other)->delete(route('analyzer.runs.transcripts.destroy', [$run, $document]))->assertForbidden();
        $this->actingAs($owner)->delete(route('analyzer.runs.transcripts.destroy', [$secondRun, $document]))->assertNotFound();
        $this->assertDatabaseHas('transcript_documents', ['id' => $document->id]);
    }

    private function completedVideoRun(User $user, string $videoId = 'dQw4w9WgXcQ', string $channelId = 'channel-transcript'): AnalyzerRun
    {
        $run = app(CreateAnalyzerRun::class)->handle($user, $videoId, CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create(['provider' => 'youtube', 'provider_channel_id' => $channelId, 'title' => 'Channel']);
        $video = Video::query()->create([
            'provider' => 'youtube', 'provider_video_id' => $videoId, 'channel_id' => $channel->id,
            'title' => 'Video', 'published_at' => now()->subDay(),
        ]);
        $snapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id, 'collection_run_id' => $run->collection_run_id,
            'view_count' => 100, 'like_count' => 10, 'comment_count' => 5, 'collected_at' => now(),
        ]);
        AnalyzerRunVideo::query()->create([
            'analyzer_run_id' => $run->id, 'video_id' => $video->id, 'video_snapshot_id' => $snapshot->id,
            'role' => 'anchor', 'source_position' => 1,
        ]);
        VideoAnalysisMetric::query()->create([
            'analyzer_run_id' => $run->id, 'video_id' => $video->id, 'age_seconds' => 86400,
            'calculation_version' => 'fixture-metrics-v1', 'input_summary' => [], 'calculated_at' => now(),
        ]);
        DB::table('analyzer_runs')->where('id', $run->id)->update([
            'video_id' => $video->id, 'channel_id' => $channel->id, 'status' => AnalyzerRunStatus::Completed->value,
            'progress_percent' => 100, 'completed_at' => now(), 'calculated_at' => now(),
        ]);

        return $run->fresh();
    }
}
