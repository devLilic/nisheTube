<?php

namespace Tests\Feature\Discovery;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Actions\LinkCandidateValidationRun;
use App\Domain\Discovery\Actions\LinkDiscoverySeedResearchRun;
use App\Domain\Discovery\Actions\QueueDiscoveryRun;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Discovery\ReadModels\CollectDiscoveryObservations;
use App\Domain\Discovery\Services\DeterministicDiscoveryEngine;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Discovery\GenerateDiscoveryCandidates;
use App\Models\Channel;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DiscoveryGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_queued_generation_uses_stored_samples_and_is_idempotent(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($user, $market, [
            ['general storage basics', 100, 0.2],
            ['home organization basics', 120, 0.3],
            ['small apartment storage ideas', 2000, 2.5],
            ['small apartment storage makeover', 2500, 3.0],
        ]);
        $analyzerRun = app(CreateAnalyzerRun::class)->handle(
            $user,
            "discovery-video-{$sample->id}-2",
            CollectionCachePolicy::AllowFreshCache,
            originKind: 'search',
            originReference: $sample->public_id,
        );
        $analyzerRun->update(['status' => AnalyzerRunStatus::Completed, 'completed_at' => now()]);
        $discovery = app(CreateDiscoveryRun::class)->handle($user, $market, ['compact living']);
        app(LinkDiscoverySeedResearchRun::class)->handle($user, $discovery->seeds->firstOrFail(), $sample);

        $queued = app(QueueDiscoveryRun::class)->handle($user, $discovery);

        $this->assertSame(DiscoveryRunStatus::Queued, $queued->status);
        Queue::assertPushed(
            GenerateDiscoveryCandidates::class,
            fn (GenerateDiscoveryCandidates $job): bool => $job->discoveryRunId === $discovery->id,
        );

        $job = new GenerateDiscoveryCandidates($discovery->id);
        $job->handle(
            app(CollectDiscoveryObservations::class),
            app(DeterministicDiscoveryEngine::class),
        );

        $completed = $discovery->fresh();
        $candidate = $completed->candidates()->where('phrase_key', 'small apartment')->firstOrFail();

        $this->assertSame(DiscoveryRunStatus::Completed, $completed->status);
        $this->assertSame(100, $completed->progress_percent);
        $this->assertSame($completed->candidates()->count(), $completed->candidate_count);
        $this->assertGreaterThan(0, $completed->candidate_count);
        $this->assertSame(DeterministicDiscoveryEngine::FORMULA_VERSION, $candidate->formula_version);
        $this->assertSame('weak_phrase_signal', $candidate->evidence_state->value);
        $this->assertSame('Weak phrase signal', $candidate->evidence['quality_label']);
        $this->assertSame(2, $candidate->evidence['source_video_count']);
        $this->assertSame([$analyzerRun->public_id], $candidate->evidence['analyzer_run_ids']);
        $this->assertSame(['research_snapshot', 'analyzer_profile'], $candidate->evidence['evidence_provenance']);
        $this->assertSame('requires_validation_search', $candidate->evidence['opportunity_score_status']);
        $this->assertSame('discovery-phrase-normalization-v1', $candidate->evidence['normalization_version']);
        $this->assertTrue(Gate::forUser($user)->allows('view', $candidate));
        $this->assertDatabaseCount('api_usage_events', 0);

        $candidateCount = NicheCandidate::query()->count();
        $job->handle(
            app(CollectDiscoveryObservations::class),
            app(DeterministicDiscoveryEngine::class),
        );

        $this->assertSame($candidateCount, NicheCandidate::query()->count());
    }

    public function test_generation_completes_with_an_explicit_empty_candidate_set_when_no_breakout_exists(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($user, $market, [
            ['steady compact homes', 100, 0.4],
            ['steady apartment tours', 100, 0.5],
            ['steady storage guides', 100, 0.6],
        ]);
        $discovery = app(CreateDiscoveryRun::class)->handle($user, $market, ['steady living']);
        app(LinkDiscoverySeedResearchRun::class)->handle($user, $discovery->seeds->firstOrFail(), $sample);
        app(QueueDiscoveryRun::class)->handle($user, $discovery);

        (new GenerateDiscoveryCandidates($discovery->id))->handle(
            app(CollectDiscoveryObservations::class),
            app(DeterministicDiscoveryEngine::class),
        );

        $this->assertSame(DiscoveryRunStatus::Completed, $discovery->fresh()->status);
        $this->assertSame(0, $discovery->fresh()->candidate_count);
        $this->assertDatabaseCount('niche_candidates', 0);
    }

    public function test_content_format_filters_use_the_stored_video_duration(): void
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($user, $market, [
            ['short storage tip', 100, 0.2],
            ['long storage guide', 200, 0.3],
        ]);
        $videos = $sample->videos()->orderBy('id')->get();
        $videos[0]->update(['duration_seconds' => 180]);
        $videos[1]->update(['duration_seconds' => 1500]);

        $discovery = app(CreateDiscoveryRun::class)->handle(
            $user,
            $market,
            ['storage ideas'],
            intakeContext: ['content_format' => 'long_form'],
        );
        app(LinkDiscoverySeedResearchRun::class)->handle($user, $discovery->seeds->firstOrFail(), $sample);

        $observations = app(CollectDiscoveryObservations::class)->handle($discovery);

        $this->assertCount(1, $observations);
        $this->assertSame($videos[1]->provider_video_id, $observations[0]->providerVideoId);
    }

    public function test_failed_generation_can_retry_without_duplicating_or_resetting_saved_candidates(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($user, $market, [
            ['general storage basics', 100, 0.2],
            ['home organization basics', 120, 0.3],
            ['small apartment storage ideas', 2000, 2.5],
            ['small apartment storage makeover', 2500, 3.0],
        ]);
        $discovery = app(CreateDiscoveryRun::class)->handle($user, $market, ['compact living']);
        app(LinkDiscoverySeedResearchRun::class)->handle($user, $discovery->seeds->firstOrFail(), $sample);
        $saved = $discovery->candidates()->create([
            'phrase' => 'small apartment',
            'cluster_key' => 'saved-cluster',
            'summary' => 'Saved before a retry.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'overall_score' => 50,
            'confidence_score' => 50,
            'formula_version' => 'discovery-breakout-v1',
            'status' => NicheCandidateStatus::Saved,
        ]);
        app(QueueDiscoveryRun::class)->handle($user, $discovery);
        $job = new GenerateDiscoveryCandidates($discovery->id);
        $job->failed(new RuntimeException('Sensitive provider detail that must not persist.'));

        $failed = $discovery->fresh();
        $this->assertSame(DiscoveryRunStatus::Failed, $failed->status);
        $this->assertSame('discovery_generation_failed', $failed->error_code);
        $this->assertStringNotContainsString('Sensitive provider detail', $failed->error_message);

        $retried = app(QueueDiscoveryRun::class)->handle($user, $failed);
        $this->assertSame(DiscoveryRunStatus::Queued, $retried->status);
        $this->assertNull($retried->error_code);
        $this->assertNull($retried->error_message);

        $job->handle(
            app(CollectDiscoveryObservations::class),
            app(DeterministicDiscoveryEngine::class),
        );

        $this->assertSame(DiscoveryRunStatus::Completed, $discovery->fresh()->status);
        $this->assertSame(1, $discovery->candidates()->where('phrase_key', 'small apartment')->count());
        $this->assertSame(NicheCandidateStatus::Saved, $saved->fresh()->status);
        $this->assertSame(50.0, $saved->fresh()->overall_score);
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_candidate_evidence_fields_cannot_be_mutated_after_creation(): void
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($user, $market, ['apartment storage']);
        $candidate = $run->candidates()->create([
            'phrase' => 'apartment storage',
            'cluster_key' => 'immutable-cluster',
            'summary' => 'Frozen evidence.',
            'evidence' => ['evidence_state' => 'candidate'],
            'overall_score' => 72,
            'confidence_score' => 68,
            'formula_version' => DeterministicDiscoveryEngine::FORMULA_VERSION,
            'evidence_state' => 'candidate',
            'status' => NicheCandidateStatus::New,
        ]);

        $candidate->update(['status' => NicheCandidateStatus::Saved]);
        $this->assertSame(NicheCandidateStatus::Saved, $candidate->fresh()->status);

        $this->expectException(DomainException::class);
        $candidate->update(['overall_score' => 99]);
    }

    public function test_candidate_validation_linkage_requires_owner_market_and_validation_kind(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $discovery = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact living']);
        $candidate = $discovery->candidates()->create([
            'phrase' => 'small apartment storage',
            'cluster_key' => 'cluster-one',
            'summary' => 'Stored observed evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'status' => NicheCandidateStatus::New,
        ]);
        $query = app(CreateResearchQuery::class)->handle($owner, $market, $candidate->phrase);
        $validation = app(CreateResearchRun::class)->handle(
            $owner,
            $query,
            25,
            ResearchRunKind::DiscoveryValidation,
        );

        $linked = app(LinkCandidateValidationRun::class)->handle($owner, $candidate, $validation);

        $this->assertSame(NicheCandidateStatus::Validated, $linked->status);
        $this->assertSame($validation->id, $linked->validation_research_run_id);

        $foreignQuery = app(CreateResearchQuery::class)->handle($otherUser, $market, 'foreign validation');
        $foreignValidation = app(CreateResearchRun::class)->handle(
            $otherUser,
            $foreignQuery,
            25,
            ResearchRunKind::DiscoveryValidation,
        );

        $this->expectException(AuthorizationException::class);
        app(LinkCandidateValidationRun::class)->handle($owner, $candidate, $foreignValidation);
    }

    public function test_candidate_validation_rejects_search_kind_and_mismatched_market(): void
    {
        $owner = User::factory()->create();
        $globalMarket = Market::query()->where('key', 'global_en')->firstOrFail();
        $romanianMarket = Market::query()->where('key', 'ro_ro')->firstOrFail();
        $discovery = app(CreateDiscoveryRun::class)->handle($owner, $globalMarket, ['compact living']);
        $candidate = $discovery->candidates()->create([
            'phrase' => 'small apartment storage',
            'cluster_key' => 'cluster-one',
            'summary' => 'Stored observed evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'status' => NicheCandidateStatus::New,
        ]);
        $searchQuery = app(CreateResearchQuery::class)->handle($owner, $globalMarket, $candidate->phrase);
        $searchRun = app(CreateResearchRun::class)->handle($owner, $searchQuery, 25);

        try {
            app(LinkCandidateValidationRun::class)->handle($owner, $candidate, $searchRun);
            $this->fail('A normal search run must not be linked as candidate validation.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'A candidate validation link requires a discovery-validation research run.',
                $exception->getMessage(),
            );
        }

        $romanianQuery = app(CreateResearchQuery::class)->handle($owner, $romanianMarket, $candidate->phrase);
        $romanianValidation = app(CreateResearchRun::class)->handle(
            $owner,
            $romanianQuery,
            25,
            ResearchRunKind::DiscoveryValidation,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Candidate validation must use the discovery run market.');
        app(LinkCandidateValidationRun::class)->handle($owner, $candidate, $romanianValidation);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: float}>  $observations
     */
    private function completedSample(User $user, Market $market, array $observations): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle($user, $market, 'seed sample');
        $run = app(CreateResearchRun::class)->handle($user, $query, count($observations));
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => 'Sample channel',
        ]);

        foreach ($observations as $index => [$title, $viewsPerDay, $reachRatio]) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => "discovery-video-{$run->id}-{$index}",
                'channel_id' => $channel->id,
                'title' => $title,
                'published_at' => '2026-08-01 12:00:00',
            ]);
            $run->videos()->attach($video->id, [
                'result_rank' => $index + 1,
                'page_number' => 1,
                'provider_order' => $index + 1,
            ]);
            $videoSnapshot = $run->videoSnapshots()->create([
                'video_id' => $video->id,
                'collection_run_id' => $run->collection_run_id,
                'view_count' => $viewsPerDay * 10,
                'views_per_day' => $viewsPerDay,
                'views_to_subscribers_ratio' => $reachRatio,
                'collected_at' => '2026-08-08 12:00:00',
            ]);
            $run->videoMemberships()
                ->where('video_id', $video->id)
                ->firstOrFail()
                ->pinSources($videoSnapshot, null);
        }

        $run->update([
            'status' => ResearchRunStatus::Completed,
            'collected_result_count' => count($observations),
            'enriched_result_count' => count($observations),
            'progress_percent' => 100,
            'completed_at' => '2026-08-08 12:00:00',
        ]);

        return $run->fresh();
    }
}
