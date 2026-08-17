<?php

namespace Tests\Feature\Navigation;

use App\Domain\Settings\Services\ResearchContextResolver;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ResearchContextNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_guest_cannot_change_research_context(): void
    {
        $this->put(route('research-context.update'), [
            'changed' => 'market',
            'market_key' => 'ro_ro',
        ])->assertRedirect(route('login'));
    }

    public function test_owner_context_persists_across_navigation_without_provider_work(): void
    {
        $owner = User::factory()->create(['default_market_key' => 'global_en']);
        $other = User::factory()->create();
        $project = $this->project($owner, 'Romanian opportunity research');
        $foreignProject = $this->project($other, 'Private competitor plan');
        $workspace = $this->workspace($owner, 'ro_ro', $project, 'Nișe educaționale foarte lungi pentru desktop truncation');
        $this->workspace($other, 'ro_ro', $foreignProject, 'Foreign workspace');

        $this->actingAs($owner)->put(route('research-context.update'), [
            'changed' => 'workspace',
            'market_key' => 'global_en',
            'project' => null,
            'workspace' => $workspace->public_id,
        ])->assertRedirect();

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('researchContext.selection.market.value', 'ro_ro')
            ->where('researchContext.selection.project.value', $project->public_id)
            ->where('researchContext.selection.workspace.value', $workspace->public_id)
            ->where('researchContext.notice', null)
            ->has('researchContext.options.projects', 1)
            ->has('researchContext.options.workspaces', 1));

        $this->get(route('history.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('researchContext.selection.workspace.value', $workspace->public_id));
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_foreign_and_archived_context_targets_are_rejected_without_leaking_them(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreignProject = $this->project($other, 'Foreign project');
        $archived = $this->project($owner, 'Archived project', archived: true);

        $this->actingAs($owner)->put(route('research-context.update'), [
            'changed' => 'project',
            'market_key' => 'global_en',
            'project' => $foreignProject->public_id,
        ])->assertSessionHasErrors('project');
        $this->put(route('research-context.update'), [
            'changed' => 'project',
            'market_key' => 'global_en',
            'project' => $archived->public_id,
        ])->assertSessionHasErrors('project');

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('researchContext.selection.project', null)
            ->has('researchContext.options.projects', 0));
    }

    public function test_stale_archived_and_incompatible_session_context_falls_back_safely(): void
    {
        $owner = User::factory()->create(['default_market_key' => 'global_en']);
        $project = $this->project($owner, 'Active project');
        $otherProject = $this->project($owner, 'Other project');
        $workspace = $this->workspace($owner, 'ro_ro', $otherProject, 'Romanian workspace');

        $this->actingAs($owner)
            ->withSession([
                ResearchContextResolver::SESSION_KEY => [
                    'user_id' => $owner->id,
                    'market_key' => 'global_en',
                    'project' => $project->public_id,
                    'workspace' => $workspace->public_id,
                ],
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('researchContext.selection.market.value', 'global_en')
                ->where('researchContext.selection.project.value', $project->public_id)
                ->where('researchContext.selection.workspace', null)
                ->where('researchContext.notice', 'The previous workspace does not match the active market and was cleared.'));

        $project->update(['archived_at' => now()]);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('researchContext.selection.project', null)
            ->where('researchContext.selection.workspace', null)
            ->where('researchContext.notice', 'The previous project is archived or unavailable and was cleared.'));
    }

    private function project(User $user, string $name, bool $archived = false): ResearchProject
    {
        return $user->researchProjects()->create([
            'name' => $name,
            'archived_at' => $archived ? now() : null,
        ]);
    }

    private function workspace(User $user, string $marketKey, ?ResearchProject $project, string $name): TopicWorkspace
    {
        $market = Market::query()->where('key', $marketKey)->firstOrFail();

        return $user->topicWorkspaces()->create([
            'research_project_id' => $project?->id,
            'market_id' => $market->id,
            'name' => $name,
            'name_key' => mb_strtolower($name),
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
        ]);
    }
}
