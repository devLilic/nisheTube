<?php

namespace Tests\Feature\Discovery;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Enums\CandidateEvidenceState;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BulkCandidateDismissTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_owner_bulk_dismissal_is_bounded_idempotent_and_preserves_validated_candidates(): void
    {
        $owner = User::factory()->create();
        $run = $this->discoveryRun($owner);
        $new = $this->candidate($run, 1, NicheCandidateStatus::New);
        $alreadyDismissed = $this->candidate($run, 2, NicheCandidateStatus::Dismissed);
        $validated = $this->candidate($run, 3, NicheCandidateStatus::Validated);
        $payload = ['candidate_ids' => [$new->public_id, $alreadyDismissed->public_id, $validated->public_id]];

        $this->actingAs($owner)
            ->post(route('discovery.candidates.bulk-dismiss', $run), $payload)
            ->assertRedirect();

        $this->assertSame(NicheCandidateStatus::Dismissed, $new->fresh()->status);
        $this->assertSame(NicheCandidateStatus::Dismissed, $alreadyDismissed->fresh()->status);
        $this->assertSame(NicheCandidateStatus::Validated, $validated->fresh()->status);

        $this->actingAs($owner)
            ->post(route('discovery.candidates.bulk-dismiss', $run), $payload)
            ->assertRedirect();

        $this->assertSame(NicheCandidateStatus::Dismissed, $new->fresh()->status);
        $this->assertSame(NicheCandidateStatus::Validated, $validated->fresh()->status);
    }

    public function test_bulk_dismissal_rejects_foreign_or_mixed_candidates_without_changing_owned_candidates(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerRun = $this->discoveryRun($owner);
        $otherRun = $this->discoveryRun($other);
        $ownerCandidate = $this->candidate($ownerRun, 1, NicheCandidateStatus::New);
        $foreignCandidate = $this->candidate($otherRun, 2, NicheCandidateStatus::New);

        $this->actingAs($owner)
            ->post(route('discovery.candidates.bulk-dismiss', $ownerRun), [
                'candidate_ids' => [$ownerCandidate->public_id, $foreignCandidate->public_id],
            ])
            ->assertSessionHasErrors('candidate_ids');

        $this->assertSame(NicheCandidateStatus::New, $ownerCandidate->fresh()->status);
        $this->assertSame(NicheCandidateStatus::New, $foreignCandidate->fresh()->status);

        $this->actingAs($other)
            ->post(route('discovery.candidates.bulk-dismiss', $ownerRun), [
                'candidate_ids' => [$ownerCandidate->public_id],
            ])
            ->assertForbidden();
    }

    public function test_bulk_dismissal_rejects_selections_above_the_visible_ten_item_limit(): void
    {
        $owner = User::factory()->create();
        $run = $this->discoveryRun($owner);
        $candidateIds = [];

        foreach (range(1, 11) as $index) {
            $candidateIds[] = $this->candidate($run, $index, NicheCandidateStatus::New)->public_id;
        }

        $this->actingAs($owner)
            ->post(route('discovery.candidates.bulk-dismiss', $run), ['candidate_ids' => $candidateIds])
            ->assertSessionHasErrors('candidate_ids');

        $this->assertSame(11, $run->candidates()->where('status', NicheCandidateStatus::New->value)->count());
    }

    private function discoveryRun(User $user): DiscoveryRun
    {
        return app(CreateDiscoveryRun::class)->handle(
            $user,
            Market::query()->where('key', 'global_en')->firstOrFail(),
            ['bulk dismissal'],
        );
    }

    private function candidate(DiscoveryRun $run, int $index, NicheCandidateStatus $status): NicheCandidate
    {
        return $run->candidates()->create([
            'phrase' => "Bulk candidate {$index}",
            'cluster_key' => "bulk-{$index}",
            'summary' => 'Stored decision evidence.',
            'evidence' => ['source_video_count' => $index],
            'overall_score' => 80,
            'confidence_score' => 70,
            'formula_version' => 'candidate-evidence-v2',
            'evidence_state' => CandidateEvidenceState::Candidate,
            'status' => $status,
        ]);
    }
}
