<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Transcripts\Contracts\TranscriptStructureProvider;
use App\Domain\Transcripts\Data\TranscriptStructureSegment;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\TranscriptDocument;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class TranscriptStructureAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_calculates_one_immutable_evidence_linked_profile_for_current_revision(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $document = $this->storeTranscript($owner, $run, $this->completeTranscript());
        $route = route('analyzer.runs.transcripts.structure.store', [$run, $document]);

        $this->actingAs($owner)->post($route)->assertRedirect(route('analyzer.runs.show', $run));
        $this->actingAs($owner)->post($route)->assertRedirect(route('analyzer.runs.show', $run));

        $this->assertDatabaseCount('transcript_structure_profiles', 1);
        $this->assertDatabaseHas('transcript_structure_profiles', [
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'transcript_document_id' => $document->id,
            'status' => 'complete',
            'provenance' => 'inferred',
            'algorithm_version' => 'transcript-structure-v1',
        ]);
        $this->assertDatabaseHas('transcript_structure_insights', ['kind' => 'summary']);
        $this->assertDatabaseHas('transcript_structure_insights', ['kind' => 'script_structure']);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.transcript.document.analysis.status', 'complete')
            ->where('run.transcript.document.analysis.provenance', 'inferred')
            ->where('run.transcript.document.analysis.algorithm_version', 'transcript-structure-v1')
            ->where('run.transcript.document.analysis.language', 'en')
            ->where('run.transcript.document.analysis.confidence_score', fn ($value): bool => is_float($value) && $value > 0)
            ->has('run.transcript.document.analysis.insights', fn (Assert $insights): Assert => $insights
                ->each(fn (Assert $insight): Assert => $insight
                    ->hasAll(['kind', 'label', 'confidence', 'start_offset', 'end_offset', 'evidence_text'])
                    ->etc())));
    }

    public function test_analysis_is_guest_protected_owner_scoped_and_revision_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $otherRun = $this->completedVideoRun($owner, '9bZkp7q19f0', 'other-channel');
        $document = $this->storeTranscript($owner, $run, $this->completeTranscript());
        $route = route('analyzer.runs.transcripts.structure.store', [$run, $document]);

        $this->app['auth']->logout();
        $this->post($route)->assertRedirect(route('login'));
        $this->actingAs($other)->post($route)->assertForbidden();
        $this->actingAs($owner)
            ->post(route('analyzer.runs.transcripts.structure.store', [$otherRun, $document]))
            ->assertNotFound();
        $this->assertDatabaseCount('transcript_structure_profiles', 0);
    }

    public function test_insufficient_and_failed_states_are_persisted_without_changing_original_transcript(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $short = $this->storeTranscript($owner, $run, 'A very short transcript.');

        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.structure.store', [$run, $short]));
        $this->assertDatabaseHas('transcript_structure_profiles', [
            'transcript_document_id' => $short->id,
            'status' => 'insufficient',
        ]);

        $secondRun = $this->completedVideoRun($owner, 'M7lc1UVf-VE', 'failure-channel');
        $failureDocument = $this->storeTranscript($owner, $secondRun, $this->completeTranscript());
        $this->app->bind(TranscriptStructureProvider::class, fn () => new class implements TranscriptStructureProvider
        {
            public function name(): string
            {
                return 'failing_fixture';
            }

            public function version(): string
            {
                return 'transcript-structure-failure-v1';
            }

            /** @param list<TranscriptStructureSegment> $segments */
            public function analyze(string $plainText, string $declaredLanguage, array $segments): never
            {
                throw new RuntimeException('sensitive provider detail');
            }
        });

        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.structure.store', [$secondRun, $failureDocument]));

        $this->assertDatabaseHas('transcript_structure_profiles', [
            'transcript_document_id' => $failureDocument->id,
            'status' => 'failed',
            'provider' => 'failing_fixture',
        ]);
        $this->assertDatabaseHas('transcript_documents', [
            'id' => $failureDocument->id,
            'plain_text' => implode("\n", array_map(
                fn (string $line): string => preg_replace('/^\[[^]]+\]\s*/', '', $line) ?? $line,
                explode("\n", $this->completeTranscript()),
            )),
        ]);
        $this->assertDatabaseMissing('transcript_structure_profiles', ['warnings' => 'sensitive provider detail']);
    }

    public function test_deleting_revision_cascades_structure_only(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $document = $this->storeTranscript($owner, $run, $this->completeTranscript());
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.structure.store', [$run, $document]));

        $this->actingAs($owner)->delete(route('analyzer.runs.transcripts.destroy', [$run, $document]));

        $this->assertDatabaseCount('transcript_structure_profiles', 0);
        $this->assertDatabaseCount('transcript_structure_insights', 0);
        $this->assertDatabaseHas('analyzer_runs', ['id' => $run->id, 'status' => 'completed']);
        $this->assertDatabaseHas('video_snapshots', ['collection_run_id' => $run->collection_run_id]);
    }

    private function storeTranscript(User $owner, AnalyzerRun $run, string $text): TranscriptDocument
    {
        $this->actingAs($owner)->post(route('analyzer.runs.transcripts.store', $run), [
            'transcript' => $text,
            'language' => 'en',
            'rights_confirmed' => true,
        ])->assertRedirect();

        return $run->transcriptDocuments()->latest('id')->firstOrFail();
    }

    private function completeTranscript(): string
    {
        return implode("\n", [
            '[0:00] OpenAI Research introduces dumpling research and a clear dumpling promise for careful creators today.',
            '[0:10] OpenAI Research compares dumpling ingredients, dumpling methods, and practical kitchen evidence for creators.',
            '[0:20] Why does dumpling dough need patient preparation and careful measurements for reliable kitchen results?',
            '[0:30] The dumpling method develops through mixing, resting, rolling, filling, folding, steaming, and final tasting.',
            '[0:40] Creators compare each dumpling result and record which kitchen method produces the clearest improvement.',
            '[0:50] This dumpling section explains repeatable preparation, useful measurements, and common kitchen mistakes.',
            '[1:00] The closing returns to dumpling research and summarizes the preparation evidence for careful creators.',
            '[1:10] Subscribe and leave a comment below for another dumpling method explained with measured evidence.',
        ]);
    }

    private function completedVideoRun(User $user, string $videoId = 'dQw4w9WgXcQ', string $channelId = 'channel-structure'): AnalyzerRun
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
        DB::table('analyzer_runs')->where('id', $run->id)->update([
            'video_id' => $video->id, 'channel_id' => $channel->id, 'status' => AnalyzerRunStatus::Completed->value,
            'progress_percent' => 100, 'completed_at' => now(), 'calculated_at' => now(),
        ]);

        return $run->fresh();
    }
}
