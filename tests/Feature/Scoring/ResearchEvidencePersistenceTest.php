<?php

namespace Tests\Feature\Scoring;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Scoring\Actions\CalculateOpportunityScore;
use App\Domain\Scoring\Services\ResearchEvidenceV1;
use App\Models\ApiUsageEvent;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\ResearchEvidenceProfile;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchEvidencePersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(MarketSeeder::class);
    }

    public function test_scoring_persists_versioned_immutable_profile_and_exact_result_sources(): void
    {
        $user = User::factory()->create();
        $query = $this->researchQuery($user);
        $run = $this->scoringRun($user, $query, [100, 90, 80, 70, 60]);

        app(CalculateOpportunityScore::class)->handle($run);

        $profile = ResearchEvidenceProfile::query()->with('results')->sole();
        $this->assertSame(ResearchEvidenceV1::VERSION, $profile->evidence_version);
        $this->assertSame(5, $profile->full_sample_count);
        $this->assertSame(4, $profile->strict_sample_count);
        $this->assertCount(5, $profile->results);
        $this->assertNotNull($profile->results->first()->video_snapshot_id);
        $this->assertSame('off_topic', $profile->results->last()->relevance_class);
        $this->assertSame('unavailable', $profile->stability_evidence['state']);
        $this->assertTrue(Schema::hasIndex(
            'research_evidence_profiles',
            ['research_run_id', 'evidence_version'],
            'unique',
        ));

        $this->expectException(DomainException::class);
        $profile->update(['full_sample_count' => 99]);
    }

    public function test_compatible_later_snapshot_persists_high_stability_without_rewriting_history(): void
    {
        $user = User::factory()->create();
        $query = $this->researchQuery($user);
        $first = $this->scoringRun($user, $query, [100, 90, 80, 70, 60]);
        app(CalculateOpportunityScore::class)->handle($first);
        $firstProfile = $first->evidenceProfiles()->sole();

        $second = $this->scoringRun($user, $query, [105, 92, 82, 72, 62]);
        app(CalculateOpportunityScore::class)->handle($second);
        $secondProfile = $second->evidenceProfiles()->sole();

        $this->assertSame('high', $secondProfile->stability_evidence['label']);
        $this->assertEquals(1.0, $secondProfile->stability_evidence['result_overlap']);
        $this->assertSame(5, $secondProfile->stability_evidence['previous_overlap_count']);
        $this->assertSame($first->id, $secondProfile->stability_evidence['previous_research_run_id']);
        $this->assertSame('unavailable', $firstProfile->fresh()->stability_evidence['state']);
        $this->assertDatabaseCount('research_evidence_profiles', 2);
        $this->assertDatabaseCount('research_result_evidence', 10);
    }

    public function test_owner_can_inspect_accessible_strict_and_aggregate_evidence_without_provider_work(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->scoringRun($owner, $this->researchQuery($owner), [100, 90, 80, 70, 60]);
        app(CalculateOpportunityScore::class)->handle($run);
        $usage = ApiUsageEvent::query()->count();

        $this->actingAs($owner)
            ->get(route('research.runs.show', [
                'researchRun' => $run,
                'evidence_filter' => 'strictly_relevant',
                'evidence_sort' => 'relevance',
                'evidence_direction' => 'desc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.evidence_profile.state', 'available')
                ->where('run.evidence_profile.version', ResearchEvidenceV1::VERSION)
                ->where('run.evidence_profile.full_sample_count', 5)
                ->where('run.evidence_profile.strict_sample_count', 4)
                ->where('run.evidence_profile.format_evidence.shorts.count', 2)
                ->where('run.evidence_profile.format_evidence.long_form.count', 3)
                ->where('run.evidence_profile.outlier_evidence.full.state', 'available')
                ->where('run.evidence_inspection.relevance.state', 'versioned')
                ->where('run.evidence_inspection.filters.1.enabled', true)
                ->where('run.evidence_inspection.pagination.total', 4)
                ->where('run.evidence_inspection.items.0.relevance.class', 'strictly_relevant')
                ->where('run.decision_summary.stability.key', 'not_measured')
            );

        $this->actingAs($other)->get(route('research.runs.show', $run))->assertForbidden();
        $this->assertSame($usage, ApiUsageEvent::query()->count());
    }

    private function researchQuery(User $user): ResearchQuery
    {
        return app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'camera guide',
        );
    }

    /** @param list<int> $viewsPerDay */
    private function scoringRun(User $user, ResearchQuery $query, array $viewsPerDay): ResearchRun
    {
        $run = app(CreateResearchRun::class)->handle(
            user: $user,
            query: $query,
            requestedResultCount: count($viewsPerDay),
            intakeContext: ['content_format' => 'any', 'language' => 'en'],
        );
        $transition = app(TransitionResearchRun::class);
        foreach ([ResearchRunStatus::Queued, ResearchRunStatus::Searching, ResearchRunStatus::Enriching] as $status) {
            $run = $transition->handle($run, $status);
        }

        foreach ($viewsPerDay as $index => $velocity) {
            $number = $index + 1;
            $channel = Channel::query()->firstOrCreate(
                ['provider' => 'youtube', 'provider_channel_id' => 'camera-channel-'.$number],
                ['title' => 'Camera channel '.$number],
            );
            $video = Video::query()->firstOrCreate(
                ['provider' => 'youtube', 'provider_video_id' => 'camera-video-'.$number],
                [
                    'channel_id' => $channel->id,
                    'title' => $number === 5 ? 'Unrelated cooking video' : 'Camera guide lesson '.$number,
                    'published_at' => now()->subDays($number),
                    'duration_seconds' => $number % 2 === 0 ? 45 : 600,
                    'is_short' => $number % 2 === 0,
                ],
            );
            $run->videos()->attach($video->id, [
                'result_rank' => $number,
                'page_number' => 1,
                'provider_order' => $number,
                'matched_query_metadata' => ['semantic_labels' => ['camera tutorial']],
            ]);
            $channelSnapshot = ChannelSnapshot::query()->create([
                'research_run_id' => $run->id,
                'collection_run_id' => $run->collection_run_id,
                'channel_id' => $channel->id,
                'subscriber_count' => 1000 * $number,
                'view_count' => 100000,
                'video_count' => 50,
                'subscriber_count_hidden' => false,
                'collected_at' => now(),
            ]);
            $videoSnapshot = VideoSnapshot::query()->create([
                'research_run_id' => $run->id,
                'collection_run_id' => $run->collection_run_id,
                'video_id' => $video->id,
                'view_count' => $velocity * 100,
                'like_count' => $velocity * 5,
                'comment_count' => $velocity,
                'age_seconds' => $number * 86400,
                'views_per_day' => $velocity,
                'views_to_subscribers_ratio' => 1,
                'collected_at' => now(),
            ]);
            $run->videoMemberships()->where('video_id', $video->id)->firstOrFail()->pinSources($videoSnapshot, $channelSnapshot);
        }

        $run->update([
            'collected_result_count' => count($viewsPerDay),
            'enriched_result_count' => count($viewsPerDay),
            'progress_percent' => 89,
        ]);

        return $transition->handle($run, ResearchRunStatus::Scoring);
    }
}
