<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Audience\Actions\CalculateAudienceSignals;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\AudienceSignalExclusion;
use App\Models\Channel;
use App\Models\CommentCollectionRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerAudienceSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_immutable_idempotent_owner_scoped_and_evidence_linked_in_ui(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id, 'analyzer_run_id' => $run->id, 'video_id' => $run->video_id,
            'provider' => 'youtube', 'provider_video_id' => $run->target_provider_id,
            'status' => CommentCollectionStatus::Completed, 'max_comments' => 20, 'page_size' => 20,
            'pages_collected' => 1, 'comments_collected' => 4, 'reply_scope' => 'top_level_only',
            'collected_at' => now(), 'completed_at' => now(),
        ]);
        foreach ([
            'How can I fix the audio problem?',
            'How can I solve this audio problem?',
            'Please make an audio problem tutorial.',
            'The audio problem does not work.',
        ] as $index => $text) {
            $collection->comments()->create(['provider_comment_id' => "comment-{$index}", 'text' => $text, 'reply_count' => 0]);
        }

        $first = app(CalculateAudienceSignals::class)->handle($collection);
        $second = app(CalculateAudienceSignals::class)->handle($collection);
        $this->assertTrue($first->is($second));
        $this->assertSame('inferred', $first->provenance);
        $this->assertSame('audience-comment-terms-v1', $first->algorithm_version);
        $this->assertDatabaseCount('audience_signal_profiles', 1);
        $this->assertDatabaseCount('audience_signal_evidence', $first->signals()->withCount('evidenceComments')->get()->sum('evidence_comments_count'));
        $this->actingAs($other)->get(route('analyzer.runs.show', $run))->assertForbidden();

        $this->expectException(DomainException::class);
        $first->update(['status' => 'failed']);
    }

    public function test_owner_projection_exposes_traceable_version_confidence_and_source_comments(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id, 'analyzer_run_id' => $run->id, 'video_id' => $run->video_id,
            'provider' => 'youtube', 'provider_video_id' => $run->target_provider_id,
            'status' => CommentCollectionStatus::Completed, 'max_comments' => 20, 'page_size' => 20,
            'pages_collected' => 1, 'comments_collected' => 3, 'reply_scope' => 'top_level_only',
            'collected_at' => now(), 'completed_at' => now(),
        ]);
        foreach (['Audio setup problem?', 'Audio setup problem?', 'Audio setup guide please'] as $index => $text) {
            $collection->comments()->create(['provider_comment_id' => "source-{$index}", 'text' => $text, 'reply_count' => 0]);
        }
        app(CalculateAudienceSignals::class)->handle($collection);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.comments.audience_signals.provenance', 'inferred')
            ->where('run.comments.audience_signals.algorithm_version', 'audience-comment-terms-v1')
            ->where('run.comments.audience_signals.language', 'en')
            ->has('run.comments.audience_signals.confidence_score')
            ->has('run.comments.audience_signals.signals', fn (Assert $signals): Assert => $signals
                ->has('0.evidence', 2)
                ->where('0.evidence.0.text', 'Audio setup problem?')
                ->etc()));
    }

    public function test_comment_collection_retention_cascades_inferred_profiles_and_evidence(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id, 'analyzer_run_id' => $run->id, 'video_id' => $run->video_id,
            'provider' => 'youtube', 'provider_video_id' => $run->target_provider_id,
            'status' => CommentCollectionStatus::Completed, 'max_comments' => 20, 'page_size' => 20,
            'pages_collected' => 1, 'comments_collected' => 3, 'reply_scope' => 'top_level_only',
            'collected_at' => now(), 'completed_at' => now(),
        ]);
        foreach (['Audio guide problem', 'Audio guide problem', 'Audio guide problem'] as $index => $text) {
            $collection->comments()->create(['provider_comment_id' => "retained-{$index}", 'text' => $text, 'reply_count' => 0]);
        }
        app(CalculateAudienceSignals::class)->handle($collection);
        $this->assertDatabaseCount('audience_signal_profiles', 1);
        $this->assertDatabaseHas('audience_signal_evidence', ['public_comment_id' => $collection->comments()->oldest('id')->value('id')]);

        $collection->delete();

        $this->assertDatabaseCount('public_comments', 0);
        $this->assertDatabaseCount('audience_signal_profiles', 0);
        $this->assertDatabaseCount('audience_signals', 0);
        $this->assertDatabaseCount('audience_signal_evidence', 0);
    }

    public function test_existing_stored_sample_can_be_calculated_explicitly_only_by_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id, 'analyzer_run_id' => $run->id, 'video_id' => $run->video_id,
            'provider' => 'youtube', 'provider_video_id' => $run->target_provider_id,
            'status' => CommentCollectionStatus::Completed, 'max_comments' => 20, 'page_size' => 20,
            'pages_collected' => 1, 'comments_collected' => 3, 'reply_scope' => 'top_level_only',
            'collected_at' => now(), 'completed_at' => now(),
        ]);
        foreach (['Audio guide question?', 'Audio guide question?', 'Audio guide suggestion'] as $index => $text) {
            $collection->comments()->create(['provider_comment_id' => "existing-{$index}", 'text' => $text, 'reply_count' => 0]);
        }

        $this->post(route('analyzer.runs.audience-signals.store', $run))->assertRedirect(route('login'));
        $this->actingAs($other)->post(route('analyzer.runs.audience-signals.store', $run))->assertForbidden();
        $this->actingAs($owner)->post(route('analyzer.runs.audience-signals.store', $run))->assertRedirect();

        $this->assertDatabaseHas('audience_signal_profiles', [
            'user_id' => $owner->id,
            'comment_collection_run_id' => $collection->id,
            'algorithm_version' => 'audience-comment-terms-v1',
        ]);
    }

    public function test_owner_can_hide_and_restore_only_an_exact_single_word_signal_without_mutating_the_profile(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedVideoRun($owner);
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id, 'analyzer_run_id' => $run->id, 'video_id' => $run->video_id,
            'provider' => 'youtube', 'provider_video_id' => $run->target_provider_id,
            'status' => CommentCollectionStatus::Completed, 'max_comments' => 20, 'page_size' => 20,
            'pages_collected' => 1, 'comments_collected' => 4, 'reply_scope' => 'top_level_only',
            'collected_at' => now(), 'completed_at' => now(),
        ]);
        foreach (['Audio setup problem', 'Audio setup problem', 'Audio setup guide', 'Audio setup guide'] as $index => $text) {
            $collection->comments()->create(['provider_comment_id' => "exclude-{$index}", 'text' => $text, 'reply_count' => 0]);
        }
        $profile = app(CalculateAudienceSignals::class)->handle($collection);
        $this->assertTrue($profile->signals()->where('label_key', 'audio')->exists());
        $this->assertTrue($profile->signals()->where('label_key', 'audio setup')->exists());

        $this->actingAs($owner)->post(route('analyzer.runs.audience-signal-exclusions.store', $run), ['word' => 'Audio'])
            ->assertRedirect(route('analyzer.runs.show', $run));
        $this->post(route('analyzer.runs.audience-signal-exclusions.store', $run), ['word' => 'audio'])
            ->assertRedirect(route('analyzer.runs.show', $run));

        $exclusion = AudienceSignalExclusion::query()->sole();
        $this->assertSame('audio', $exclusion->normalized_word);
        $this->assertTrue($exclusion->is_active);
        $this->assertDatabaseCount('audience_signal_profiles', 1);
        $this->assertDatabaseCount('audience_signals', $profile->signals()->count());

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.comments.audience_signals.hidden_signal_count', fn (int $count): bool => $count > 0)
            ->has('run.comments.audience_signals.excluded_words', 1)
            ->where('run.comments.audience_signals.signals', fn ($signals): bool => collect($signals)->doesntContain('label', 'Audio')
                && collect($signals)->contains('label', 'Audio Setup'))
            ->etc());

        $this->actingAs($owner)->post(route('analyzer.runs.audience-signal-exclusions.store', $run), ['word' => 'Audio setup'])
            ->assertSessionHasErrors('word');
        $this->actingAs($other)->post(route('analyzer.runs.audience-signal-exclusions.store', $run), ['word' => 'Audio'])
            ->assertForbidden();
        $this->actingAs($other)->delete(route('analyzer.runs.audience-signal-exclusions.destroy', [$run, $exclusion]))
            ->assertForbidden();

        $this->actingAs($owner)->delete(route('analyzer.runs.audience-signal-exclusions.destroy', [$run, $exclusion]))
            ->assertRedirect(route('analyzer.runs.show', $run));
        $this->assertFalse($exclusion->fresh()->is_active);
        $this->assertNotNull($exclusion->fresh()->restored_at);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertInertia(fn (Assert $page): Assert => $page
            ->where('run.comments.audience_signals.hidden_signal_count', 0)
            ->has('run.comments.audience_signals.excluded_words', 0)
            ->where('run.comments.audience_signals.signals', fn ($signals): bool => collect($signals)->contains('label', 'Audio'))
            ->etc());
    }

    private function completedVideoRun(User $user): AnalyzerRun
    {
        $run = app(CreateAnalyzerRun::class)->handle($user, 'dQw4w9WgXcQ', CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create(['provider' => 'youtube', 'provider_channel_id' => 'channel-audience', 'title' => 'Channel']);
        $video = Video::query()->create([
            'provider' => 'youtube', 'provider_video_id' => 'dQw4w9WgXcQ', 'channel_id' => $channel->id,
            'title' => 'Audience Video', 'published_at' => now()->subDay(),
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
