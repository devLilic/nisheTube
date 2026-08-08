<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Domain\Research\Exceptions\InvalidResearchRunTransition;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ResearchSchemaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_research_query_and_run_store_normalized_and_frozen_inputs(): void
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'ro_ro')->firstOrFail();
        $project = ResearchProject::query()->create([
            'user_id' => $user->id,
            'name' => 'Romanian creator research',
            'color' => '#0f766e',
        ]);

        $query = $this->createQuery(
            user: $user,
            market: $market,
            project: $project,
            queryText: '  DIY   mobilier  ',
        );
        $run = app(CreateResearchRun::class)->handle($user, $query, 100);

        $this->assertTrue(Str::isUuid($project->public_id));
        $this->assertTrue(Str::isUuid($query->public_id));
        $this->assertTrue(Str::isUuid($run->public_id));
        $this->assertSame('public_id', $run->getRouteKeyName());
        $this->assertSame('DIY mobilier', $query->query_text);
        $this->assertSame('diy mobilier', $query->query_key);
        $this->assertSame(ResearchRunStatus::Draft, $run->status);
        $this->assertSame(1, $run->attempt_number);
        $this->assertSame('DIY mobilier', $run->query_text);
        $this->assertSame('ro_ro', $run->market_key);
        $this->assertSame('RO', $run->region_code);
        $this->assertSame('ro', $run->relevance_language);
        $this->assertSame(100, $run->requested_result_count);
        $this->assertSame([
            'search_order' => 'viewCount',
            'published_after' => '2026-06-30T21:00:00+00:00',
            'published_before' => '2026-07-31T20:59:59+00:00',
            'video_duration' => 'medium',
            'video_category_id' => '26',
        ], $run->parameters);

        $query->update([
            'query_text' => 'Changed query',
            'search_order' => SearchOrder::Date,
        ]);
        $market->update([
            'region_code' => 'MD',
            'relevance_language' => 'en',
        ]);

        $run->refresh();

        $this->assertSame('DIY mobilier', $run->query_text);
        $this->assertSame('RO', $run->region_code);
        $this->assertSame('ro', $run->relevance_language);
        $this->assertSame('viewCount', $run->parameters['search_order']);
    }

    public function test_new_runs_increment_attempt_number_without_overwriting_history(): void
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = $this->createQuery($user, $market, queryText: 'creator tools');
        $createRun = app(CreateResearchRun::class);

        $first = $createRun->handle($user, $query, 25);
        $second = $createRun->handle($user, $query, 50);

        $this->assertSame(1, $first->attempt_number);
        $this->assertSame(2, $second->attempt_number);
        $this->assertNotSame($first->public_id, $second->public_id);
        $this->assertSame(25, $first->fresh()->requested_result_count);
        $this->assertSame(50, $second->requested_result_count);
    }

    public function test_policies_allow_only_the_owning_user_for_research_aggregates(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $market = Market::query()->where('key', 'ru_ru')->firstOrFail();
        $project = ResearchProject::query()->create([
            'user_id' => $owner->id,
            'name' => 'Russian market',
        ]);
        $query = $this->createQuery($owner, $market, $project, 'video editing');
        $run = app(CreateResearchRun::class)->handle($owner, $query, 50);

        foreach ([$project, $query, $run] as $aggregate) {
            $this->assertTrue(Gate::forUser($owner)->allows('view', $aggregate));
            $this->assertTrue(Gate::forUser($owner)->allows('update', $aggregate));
            $this->assertFalse(Gate::forUser($otherUser)->allows('view', $aggregate));
            $this->assertFalse(Gate::forUser($otherUser)->allows('update', $aggregate));
            $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $aggregate));
        }

        $this->assertCount(1, $owner->researchProjects);
        $this->assertCount(0, $otherUser->researchProjects);
        $this->assertCount(1, $owner->researchQueries);
        $this->assertCount(1, $owner->researchRuns);
    }

    public function test_domain_actions_reject_cross_user_projects_and_queries(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $foreignProject = ResearchProject::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Private project',
        ]);

        try {
            $this->createQuery($owner, $market, $foreignProject, 'private topic');
            $this->fail('A foreign project should not be attachable to a query.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('research_queries', 0);
        }

        $foreignQuery = $this->createQuery($otherUser, $market, queryText: 'other research');

        $this->expectException(AuthorizationException::class);

        app(CreateResearchRun::class)->handle($owner, $foreignQuery, 25);
    }

    public function test_run_lifecycle_records_transition_timestamps_and_terminal_completion(): void
    {
        CarbonImmutable::setTestNow('2026-08-07 10:00:00 UTC');

        $run = $this->newRun();
        $transition = app(TransitionResearchRun::class);

        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);
        $this->assertSame('2026-08-07 10:00:00', $run->started_at?->format('Y-m-d H:i:s'));

        CarbonImmutable::setTestNow('2026-08-07 10:05:00 UTC');
        $run = $transition->handle($run, ResearchRunStatus::Enriching);
        $this->assertSame('2026-08-07 10:05:00', $run->search_completed_at?->format('Y-m-d H:i:s'));

        $run = $transition->handle($run, ResearchRunStatus::Scoring);
        CarbonImmutable::setTestNow('2026-08-07 10:06:00 UTC');
        $run = $transition->handle($run, ResearchRunStatus::Completed);

        $this->assertSame(ResearchRunStatus::Completed, $run->status);
        $this->assertSame(100, $run->progress_percent);
        $this->assertSame('2026-08-07 10:06:00', $run->completed_at?->format('Y-m-d H:i:s'));
        $this->assertNull($run->failed_at);
    }

    public function test_invalid_transitions_and_mutation_of_frozen_or_terminal_runs_are_rejected(): void
    {
        $run = $this->newRun();

        try {
            app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Searching);
            $this->fail('Skipping lifecycle states should fail.');
        } catch (InvalidResearchRunTransition) {
            $this->assertSame(ResearchRunStatus::Draft, $run->fresh()->status);
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Frozen research run parameters cannot be changed.');

        $run->update(['query_text' => 'mutated']);
    }

    public function test_failed_runs_store_only_validated_safe_failure_details_and_become_immutable(): void
    {
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($this->newRun(), ResearchRunStatus::Queued);
        $run = $transition->handle(
            $run,
            ResearchRunStatus::Failed,
            new RunFailure('youtube_quota_exhausted', 'The local quota estimate is exhausted. Retry after reset.'),
        );

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame('youtube_quota_exhausted', $run->error_code);
        $this->assertNotNull($run->failed_at);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Completed and failed research runs are immutable.');

        $run->update(['progress_percent' => 10]);
    }

    public function test_failed_transition_requires_validated_failure_details(): void
    {
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($this->newRun(), ResearchRunStatus::Queued);

        try {
            $transition->handle($run, ResearchRunStatus::Failed);
            $this->fail('A failed transition should require safe failure details.');
        } catch (InvalidArgumentException) {
            $this->assertSame(ResearchRunStatus::Queued, $run->fresh()->status);
        }

        $this->expectException(InvalidArgumentException::class);

        new RunFailure('unsafe error code', 'This must not be accepted.');
    }

    private function newRun(): ResearchRun
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = $this->createQuery($user, $market, queryText: 'local research');

        return app(CreateResearchRun::class)->handle($user, $query, 25);
    }

    private function createQuery(
        User $user,
        Market $market,
        ?ResearchProject $project = null,
        string $queryText = 'creator research',
    ): ResearchQuery {
        return app(CreateResearchQuery::class)->handle(
            user: $user,
            market: $market,
            queryText: $queryText,
            project: $project,
            searchOrder: SearchOrder::ViewCount,
            publishedAfter: CarbonImmutable::parse('2026-07-01 00:00:00', 'Europe/Chisinau'),
            publishedBefore: CarbonImmutable::parse('2026-07-31 23:59:59', 'Europe/Chisinau'),
            videoDuration: VideoDurationFilter::Medium,
            videoCategoryId: '26',
        );
    }
}
