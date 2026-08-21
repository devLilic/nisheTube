<?php

namespace Tests\Feature\Ideas;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Comments\Actions\SaveCommentIdea;
use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\CommentCollectionRun;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\PublicComment;
use App\Models\SavedCommentIdea;
use App\Models\TopicWorkspace;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SavedCommentIdeasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_owner_can_idempotently_save_comment_and_see_video_link_in_analyzer_and_ideas(): void
    {
        $owner = User::factory()->create();
        [$run, $video] = $this->completedVideoRun($owner);
        [$collection, $comment] = $this->comment($owner, $run, $video);

        $this->actingAs($owner)->post(route('ideas.comments.store', $comment))->assertRedirect();
        $this->actingAs($owner)->post(route('ideas.comments.store', $comment))->assertRedirect();

        $idea = SavedCommentIdea::query()->sole();
        $this->assertSame($owner->id, $idea->user_id);
        $this->assertSame($comment->text, $idea->comment_text);
        $this->assertSame($video->id, $idea->video_id);
        $this->assertSame($comment->id, $idea->public_comment_id);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.comments.items.0.id', $comment->id)
                ->where('run.comments.items.0.is_saved', true)
                ->where('run.comments.items.0.saved_idea_public_id', $idea->public_id));

        $this->actingAs($owner)->get(route('ideas.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ideas/index')
                ->has('items', 1)
                ->where('items.0.text', $comment->text)
                ->where('items.0.source_comment_available', true)
                ->where('items.0.video.title', 'Ideas source video')
                ->where('items.0.video.youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                ->where('pagination.per_page', 20));

        $this->assertDatabaseCount('saved_comment_ideas', 1);
        $this->assertDatabaseCount('api_usage_events', 0);
        $this->assertSame($collection->id, $comment->comment_collection_run_id);
    }

    public function test_guest_and_foreign_users_cannot_save_list_or_remove_another_owners_ideas(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$run, $video] = $this->completedVideoRun($owner);
        [, $comment] = $this->comment($owner, $run, $video);

        $this->post(route('ideas.comments.store', $comment))->assertRedirect(route('login'));
        $this->actingAs($other)->post(route('ideas.comments.store', $comment))->assertNotFound();
        $this->actingAs($owner)->post(route('ideas.comments.store', $comment))->assertRedirect();
        $idea = SavedCommentIdea::query()->sole();

        $this->actingAs($other)->get(route('ideas.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->has('items', 0));
        $this->actingAs($other)->delete(route('ideas.destroy', $idea))->assertForbidden();
        auth()->logout();
        $this->get(route('ideas.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)->delete(route('ideas.destroy', $idea))->assertRedirect();
        $this->assertDatabaseCount('saved_comment_ideas', 0);
        $this->assertDatabaseHas('public_comments', ['id' => $comment->id]);
    }

    public function test_saved_copy_and_video_link_survive_raw_comment_retention(): void
    {
        $owner = User::factory()->create();
        [$run, $video] = $this->completedVideoRun($owner);
        [$collection, $comment] = $this->comment($owner, $run, $video);
        $this->actingAs($owner)->post(route('ideas.comments.store', $comment))->assertRedirect();

        $collection->delete();
        $idea = SavedCommentIdea::query()->sole();

        $this->assertNull($idea->public_comment_id);
        $this->assertSame('A useful audience idea', $idea->comment_text);
        $this->actingAs($owner)->get(route('ideas.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.source_comment_available', false)
                ->where('items.0.video.provider_video_id', 'dQw4w9WgXcQ'));
    }

    public function test_ideas_list_is_deterministically_bounded_to_twenty_items_per_page(): void
    {
        $owner = User::factory()->create();
        [$run, $video] = $this->completedVideoRun($owner);
        [$collection] = $this->comment($owner, $run, $video);

        foreach (range(1, 21) as $index) {
            $comment = PublicComment::query()->create([
                'comment_collection_run_id' => $collection->id,
                'provider_comment_id' => "idea-comment-{$index}",
                'text' => "Idea {$index}",
                'like_count' => $index,
                'reply_count' => 0,
                'published_at' => now()->subMinutes($index),
            ]);
            app(SaveCommentIdea::class)->handle($owner, $comment);
        }

        $this->actingAs($owner)->get(route('ideas.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('items', 1)
                ->where('pagination.current_page', 2)
                ->where('pagination.total', 21)
                ->where('pagination.last_page', 2)
                ->where('pagination.from', 21)
                ->where('pagination.to', 21));
    }

    public function test_owner_can_add_compatible_decision_context_without_mutating_the_saved_comment_source(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$run, $video] = $this->completedVideoRun($owner);
        [$collection, $comment] = $this->comment($owner, $run, $video);
        $this->actingAs($owner)->post(route('ideas.comments.store', $comment));
        $idea = SavedCommentIdea::query()->sole();
        [$workspace, $candidate] = $this->decisionContext($owner, 'global_en');
        [, $foreignCandidate] = $this->decisionContext($other, 'global_en');
        [, $incompatibleCandidate] = $this->decisionContext($owner, 'ro_ro');

        $payload = [
            'workspace' => $workspace->public_id,
            'candidate' => $candidate->public_id,
            'decision_status' => 'selected',
            'format' => 'Tutorial',
            'audience' => 'New creators',
            'decision_note' => 'Keep the original audience wording.',
        ];
        $this->actingAs($other)->patch(route('ideas.update', $idea), ['decision_status' => 'new'])->assertForbidden();
        $this->actingAs($other)->patch(route('ideas.update', $idea), $payload)->assertSessionHasErrors(['workspace', 'candidate']);
        $this->actingAs($owner)->patch(route('ideas.update', $idea), [
            ...$payload, 'candidate' => $foreignCandidate->public_id,
        ])->assertSessionHasErrors('candidate');
        $this->actingAs($owner)->patch(route('ideas.update', $idea), [
            ...$payload, 'candidate' => $incompatibleCandidate->public_id,
        ])->assertSessionHasErrors('candidate');
        $this->actingAs($owner)->patch(route('ideas.update', $idea), $payload)->assertRedirect();

        $this->assertDatabaseHas('saved_comment_ideas', [
            'id' => $idea->id,
            'topic_workspace_id' => $workspace->id,
            'niche_candidate_id' => $candidate->id,
            'decision_status' => 'selected',
            'format' => 'Tutorial',
            'audience' => 'New creators',
        ]);
        $this->assertSame($comment->id, $idea->fresh()->public_comment_id);
        $this->assertSame($comment->text, $idea->fresh()->comment_text);

        $collection->delete();
        $this->actingAs($owner)->get(route('ideas.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('items.0.source_comment_available', false)
            ->where('items.0.context.workspace.public_id', $workspace->public_id)
            ->where('items.0.context.candidate.public_id', $candidate->public_id)
            ->where('items.0.context.decision_status', 'selected')
            ->has('context_options.workspaces', 2)
            ->has('context_options.candidates', 2));
    }

    /** @return array{AnalyzerRun, Video} */
    private function completedVideoRun(User $user): array
    {
        $run = app(CreateAnalyzerRun::class)->handle($user, 'dQw4w9WgXcQ', CollectionCachePolicy::ForceRefresh);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'ideas-channel',
            'title' => 'Ideas source channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'dQw4w9WgXcQ',
            'channel_id' => $channel->id,
            'title' => 'Ideas source video',
            'published_at' => now()->subDay(),
        ]);
        $snapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'collection_run_id' => $run->collection_run_id,
            'view_count' => 100,
            'like_count' => 10,
            'comment_count' => 5,
            'collected_at' => now(),
        ]);
        AnalyzerRunVideo::query()->create([
            'analyzer_run_id' => $run->id,
            'video_id' => $video->id,
            'video_snapshot_id' => $snapshot->id,
            'role' => 'anchor',
            'source_position' => 1,
        ]);
        DB::table('analyzer_runs')->where('id', $run->id)->update([
            'video_id' => $video->id,
            'channel_id' => $channel->id,
            'status' => AnalyzerRunStatus::Completed->value,
            'progress_percent' => 100,
            'completed_at' => now(),
            'calculated_at' => now(),
        ]);

        return [$run->fresh(), $video];
    }

    /** @return array{CommentCollectionRun, PublicComment} */
    private function comment(User $owner, AnalyzerRun $run, Video $video): array
    {
        $collection = CommentCollectionRun::query()->create([
            'user_id' => $owner->id,
            'analyzer_run_id' => $run->id,
            'video_id' => $video->id,
            'provider' => 'youtube',
            'provider_video_id' => $video->provider_video_id,
            'status' => 'completed',
            'max_comments' => 100,
            'page_size' => 100,
            'pages_collected' => 1,
            'comments_collected' => 1,
            'reply_scope' => 'top_level_only',
            'collected_at' => now(),
        ]);
        $comment = PublicComment::query()->create([
            'comment_collection_run_id' => $collection->id,
            'provider_comment_id' => 'useful-comment',
            'text' => 'A useful audience idea',
            'like_count' => 7,
            'reply_count' => 2,
            'published_at' => now()->subHour(),
        ]);

        return [$collection, $comment];
    }

    /** @return array{TopicWorkspace, NicheCandidate} */
    private function decisionContext(User $user, string $marketKey): array
    {
        $market = Market::query()->where('key', $marketKey)->firstOrFail();
        $workspace = TopicWorkspace::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'name' => "{$market->name} workspace {$user->id}",
            'name_key' => "{$marketKey}-workspace-{$user->id}",
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
        ]);
        $discovery = app(CreateDiscoveryRun::class)->handle($user, $market, ['decision context']);
        $candidate = $discovery->candidates()->create([
            'phrase' => "{$market->name} candidate {$user->id}",
            'cluster_key' => "{$marketKey}-candidate-{$user->id}",
            'summary' => 'Stored discovery evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'status' => NicheCandidateStatus::New,
        ]);

        return [$workspace, $candidate];
    }
}
