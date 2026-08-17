<?php

namespace Tests\Feature\Discovery;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Actions\LinkDiscoverySeedResearchRun;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscoverySchemaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_discovery_tables_expose_required_columns_constraints_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('discovery_runs', [
            'public_id', 'user_id', 'research_project_id', 'market_id', 'status', 'market_key',
            'region_code', 'relevance_language', 'parameters', 'seed_count', 'candidate_count',
            'progress_percent', 'started_at', 'completed_at', 'failed_at', 'error_code', 'error_message',
        ]));
        $this->assertTrue(Schema::hasColumns('discovery_seeds', [
            'discovery_run_id', 'seed_query', 'seed_key', 'source', 'research_run_id',
        ]));
        $this->assertTrue(Schema::hasColumns('niche_candidates', [
            'public_id', 'discovery_run_id', 'phrase', 'phrase_key', 'cluster_key', 'summary',
            'evidence', 'overall_score', 'confidence_score', 'formula_version', 'status',
            'validation_research_run_id', 'evidence_state',
        ]));
        $this->assertTrue(Schema::hasIndex('discovery_runs', ['user_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('discovery_seeds', ['discovery_run_id', 'seed_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('niche_candidates', ['discovery_run_id', 'phrase_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('niche_candidates', ['discovery_run_id', 'status', 'overall_score']));
        $this->assertTrue(Schema::hasIndex('niche_candidates', ['discovery_run_id', 'evidence_state', 'overall_score']));
    }

    public function test_creation_normalizes_unique_seeds_and_freezes_owner_market_and_parameters(): void
    {
        $user = User::factory()->create();
        $project = ResearchProject::query()->create(['user_id' => $user->id, 'name' => 'Discovery project']);
        $market = Market::query()->where('key', 'ro_ro')->firstOrFail();

        $run = app(CreateDiscoveryRun::class)->handle(
            $user,
            $market,
            ['  Case mici  ', 'case mici', 'Organizare apartament'],
            $project,
        );

        $this->assertSame($user->id, $run->user_id);
        $this->assertSame($project->id, $run->research_project_id);
        $this->assertSame(DiscoveryRunStatus::Draft, $run->status);
        $this->assertSame('ro_ro', $run->market_key);
        $this->assertSame('RO', $run->region_code);
        $this->assertSame('ro', $run->relevance_language);
        $this->assertSame(2, $run->seed_count);
        $this->assertSame('candidate-evidence-v2', $run->parameters['formula_version']);
        $this->assertSame(3, $run->parameters['candidate_evidence_thresholds']['minimum_videos']);
        $this->assertSame(
            ['Case mici', 'Organizare apartament'],
            $run->seeds->pluck('seed_query')->all(),
        );

        $this->expectException(DomainException::class);
        $run->update(['market_key' => 'global_en']);
    }

    public function test_discovery_ownership_is_enforced_by_policies_and_link_actions(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact living']);
        $seed = $run->seeds->firstOrFail();
        $foreignQuery = app(CreateResearchQuery::class)->handle($otherUser, $market, 'foreign sample');
        $foreignResearchRun = app(CreateResearchRun::class)->handle($otherUser, $foreignQuery, 10);
        $foreignResearchRun->update([
            'status' => ResearchRunStatus::Completed,
            'completed_at' => now(),
            'progress_percent' => 100,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $run));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $run));

        $this->expectException(AuthorizationException::class);
        app(LinkDiscoverySeedResearchRun::class)->handle($owner, $seed, $foreignResearchRun);
    }

    public function test_discovery_rejects_empty_seed_sets_and_foreign_projects(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $foreignProject = ResearchProject::query()->create(['user_id' => $otherUser->id, 'name' => 'Private']);

        try {
            app(CreateDiscoveryRun::class)->handle($owner, $market, []);
            $this->fail('An empty discovery seed set must be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseCount('discovery_runs', 0);
        }

        $this->expectException(AuthorizationException::class);
        app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact living'], $foreignProject);
    }
}
