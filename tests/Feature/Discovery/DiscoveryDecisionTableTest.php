<?php

namespace Tests\Feature\Discovery;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Enums\CandidateEvidenceState;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscoveryDecisionTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_owner_sees_separate_deterministic_paginated_decision_tables_without_provider_work(): void
    {
        $owner = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact storage']);
        $run->update(['status' => DiscoveryRunStatus::Completed, 'completed_at' => now()]);

        foreach (range(1, 12) as $index) {
            $this->candidate($run, $index, CandidateEvidenceState::Candidate);
            $this->candidate($run, $index + 20, CandidateEvidenceState::WeakPhraseSignal);
        }

        $this->candidate($run, 40, CandidateEvidenceState::Legacy);

        $response = $this->actingAs($owner)->get(route('discovery.runs.show', [
            'discoveryRun' => $run,
            'candidate_sort' => 'videos',
            'candidate_direction' => 'desc',
            'candidate_page' => 2,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('discovery/show')
            ->where('candidate_table.filters.sort', 'videos')
            ->where('candidate_table.filters.direction', 'desc')
            ->where('candidate_table.candidate_niches.current_page', 2)
            ->where('candidate_table.candidate_niches.per_page', 10)
            ->where('candidate_table.candidate_niches.total', 13)
            ->has('candidate_table.candidate_niches.data', 3)
            ->where('candidate_table.weak_phrase_signals.current_page', 1)
            ->where('candidate_table.weak_phrase_signals.total', 12)
            ->has('candidate_table.weak_phrase_signals.data', 10)
            ->where('candidate_table.weak_phrase_signals.data.0.evidence_state', 'weak_phrase_signal')
        );

        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_table_filters_are_validated_and_foreign_runs_remain_hidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact storage']);

        $this->actingAs($owner)
            ->get(route('discovery.runs.show', [$run, 'candidate_sort' => 'unsupported']))
            ->assertSessionHasErrors('candidate_sort');

        $this->actingAs($other)
            ->get(route('discovery.runs.show', $run))
            ->assertForbidden();
    }

    private function candidate(DiscoveryRun $run, int $index, CandidateEvidenceState $state): void
    {
        $run->candidates()->create([
            'phrase' => sprintf('Theme %02d %s', $index, $state->value),
            'cluster_key' => "cluster-{$state->value}-{$index}",
            'summary' => 'Stored decision evidence.',
            'evidence' => [
                'source_video_count' => $index,
                'unique_channel_count' => 2,
                'small_channel_proof_count' => 1,
                'typical_median_views_per_day' => 125.5,
                'outlier_free_median_views_per_day' => 110.25,
                'stability_score' => 72.5,
                'video_ids' => ["video-{$index}"],
                'channel_ids' => ["channel-{$index}"],
                'insufficiency_reasons' => $state === CandidateEvidenceState::WeakPhraseSignal
                    ? ['Calculated confidence is below the frozen threshold.']
                    : [],
            ],
            'overall_score' => 90 - $index,
            'confidence_score' => 80 - $index,
            'formula_version' => 'candidate-evidence-v2',
            'evidence_state' => $state,
            'status' => NicheCandidateStatus::New,
        ]);
    }
}
