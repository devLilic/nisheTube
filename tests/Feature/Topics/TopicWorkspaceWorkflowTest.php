<?php

namespace Tests\Feature\Topics;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Topics\Enums\TopicEvidenceRole;
use App\Domain\Topics\Enums\TopicEvidenceType;
use App\Models\Channel;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TopicWorkspaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
        Queue::fake();
    }

    public function test_owner_can_create_edit_archive_restore_and_list_market_scoped_workspaces(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['name' => 'Compact Living', 'description' => 'Small-space formats.', 'market_key' => 'global_en'];

        $this->actingAs($owner)->post(route('topics.store'), $payload)->assertRedirect();
        $workspace = TopicWorkspace::query()->sole();
        $this->assertSame('Compact Living', $workspace->name);
        $this->assertSame('compact living', $workspace->name_key);
        $this->assertSame('en', $workspace->relevance_language);

        $this->actingAs($owner)->post(route('topics.store'), [...$payload, 'name' => ' compact   living '])->assertSessionHasErrors('name_key');
        $this->actingAs($other)->patch(route('topics.update', $workspace), $payload)->assertForbidden();
        $this->actingAs($owner)->patch(route('topics.update', $workspace), [...$payload, 'name' => 'Compact Homes'])->assertRedirect();
        $this->assertSame('Compact Homes', $workspace->fresh()->name);
        $this->actingAs($owner)->post(route('topics.archive', $workspace))->assertRedirect();
        $this->assertNotNull($workspace->fresh()->archived_at);
        $this->actingAs($owner)->post(route('topics.restore', $workspace))->assertRedirect();

        $this->actingAs($owner)->get(route('topics.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('topics/index')->has('workspaces', 1)->where('workspaces.0.public_id', $workspace->public_id)
            ->where('counts.active', 1));
    }

    public function test_evidence_is_allow_listed_owner_scoped_duplicate_safe_and_cross_market_aware(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = $this->workspace($owner, 'global_en');
        $foreignRun = $this->completedRun($other, 'global_en', 'Private evidence');
        $crossMarketRun = $this->completedRun($owner, 'ro_ro', 'Romanian compact homes');
        $this->assertSame(
            ['video', 'channel', 'research_query', 'research_run', 'niche_candidate', 'analyzer_run', 'watchlist_item'],
            array_column(TopicEvidenceType::cases(), 'value'),
        );
        $this->assertSame(
            ['evidence', 'example', 'outlier', 'competitor', 'inspiration', 'counterexample'],
            array_column(TopicEvidenceRole::cases(), 'value'),
        );

        $this->actingAs($owner)->post(route('topics.evidence.store', $workspace), [
            'target_type' => 'research_run', 'target_reference' => $foreignRun->public_id,
            'evidence_role' => 'competitor',
        ])->assertNotFound();
        $this->actingAs($owner)->post(route('topics.evidence.store', $workspace), [
            'target_type' => 'arbitrary_model', 'target_reference' => $crossMarketRun->public_id,
            'evidence_role' => 'competitor',
        ])->assertSessionHasErrors('target_type');

        $payload = ['target_type' => 'research_run', 'target_reference' => $crossMarketRun->public_id, 'evidence_role' => 'counterexample', 'note' => 'Compare regional framing.'];
        $this->actingAs($owner)->post(route('topics.evidence.store', $workspace), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('topics.evidence.store', $workspace), $payload)->assertRedirect();
        $this->assertDatabaseCount('topic_workspace_items', 1);

        $this->actingAs($owner)->get(route('topics.show', $workspace))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('topics/show')->has('items', 1)
            ->where('items.0.role', 'counterexample')
            ->where('items.0.cross_market_warning', 'This evidence was collected for ro_ro, not global_en.')
            ->where('decision_canvas.coverage.linked_count', 1)
            ->where('decision_canvas.coverage.cross_market_count', 1)
            ->where('decision_canvas.coverage.same_market_completed_run_count', 0)
            ->where('decision_canvas.next_action.label', 'Queue a same-market Search'));

        $this->actingAs($owner)->post(route('topics.archive', $workspace));
        $this->actingAs($owner)->post(route('topics.evidence.store', $workspace), $payload)->assertForbidden();
    }

    public function test_confirmed_search_and_discovery_launches_are_owner_scoped_and_linked_without_metric_copies(): void
    {
        $owner = User::factory()->create(['default_result_depth' => 25]);
        $other = User::factory()->create();
        $workspace = $this->workspace($owner, 'global_en');

        $this->actingAs($other)->post(route('topics.launch.search', $workspace), ['confirmed' => true, 'query_text' => 'Compact living'])->assertForbidden();
        $this->actingAs($owner)->post(route('topics.launch.search', $workspace), ['confirmed' => false, 'query_text' => 'Compact living'])->assertSessionHasErrors('confirmed');
        $this->actingAs($owner)->post(route('topics.launch.search', $workspace), ['confirmed' => true, 'query_text' => 'Compact living'])->assertRedirect();

        $launchedSearch = ResearchRun::query()->sole();
        $this->assertSame('global_en', $launchedSearch->market_key);
        $this->assertDatabaseHas('topic_workspace_items', ['topic_workspace_id' => $workspace->id, 'target_type' => 'research_run', 'target_id' => $launchedSearch->id]);
        $this->assertDatabaseHas('topic_workspace_launches', ['launch_type' => 'search', 'research_run_id' => $launchedSearch->id]);
        $this->assertArrayNotHasKey('metrics', $workspace->items()->sole()->getAttributes());

        $sample = $this->completedRun($owner, 'global_en', 'Stored compact living sample');
        $workspace->items()->create(['target_type' => 'research_run', 'target_id' => $sample->id, 'evidence_role' => 'evidence']);
        $this->actingAs($owner)->post(route('topics.launch.discovery', $workspace), ['confirmed' => true, 'research_run' => $sample->public_id])->assertRedirect();

        $discovery = DiscoveryRun::query()->sole();
        $this->assertSame('global_en', $discovery->market_key);
        $this->assertSame($sample->id, $discovery->seeds()->sole()->research_run_id);
        $this->assertDatabaseHas('topic_workspace_launches', ['launch_type' => 'discover', 'discovery_run_id' => $discovery->id]);
    }

    private function workspace(User $user, string $marketKey): TopicWorkspace
    {
        $market = Market::query()->where('key', $marketKey)->firstOrFail();

        return $user->topicWorkspaces()->create([
            'market_id' => $market->id, 'name' => 'Compact Living', 'name_key' => 'compact living',
            'market_key' => $market->key, 'region_code' => $market->region_code, 'relevance_language' => $market->relevance_language,
        ]);
    }

    private function completedRun(User $user, string $marketKey, string $queryText): ResearchRun
    {
        $market = Market::query()->where('key', $marketKey)->firstOrFail();
        $query = app(CreateResearchQuery::class)->handle($user, $market, $queryText);
        $run = app(CreateResearchRun::class)->handle($user, $query, 25);
        $channel = Channel::query()->create(['provider' => 'youtube', 'provider_channel_id' => 'UC'.str_pad((string) $run->id, 22, 'A'), 'title' => 'Evidence channel']);
        $video = Video::query()->create(['provider' => 'youtube', 'provider_video_id' => str_pad((string) $run->id, 11, 'v'), 'channel_id' => $channel->id, 'title' => 'Evidence video', 'published_at' => now()->subDays(7)]);
        VideoSnapshot::query()->create(['video_id' => $video->id, 'research_run_id' => $run->id, 'collection_run_id' => $run->collection_run_id, 'view_count' => 100, 'collected_at' => now()]);
        $run->update(['status' => ResearchRunStatus::Completed, 'progress_percent' => 100, 'collected_result_count' => 1, 'enriched_result_count' => 1, 'completed_at' => now()]);

        return $run;
    }
}
