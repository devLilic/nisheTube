<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class LinkCandidateValidationRun
{
    /** @throws AuthorizationException */
    public function handle(User $user, NicheCandidate $candidate, ResearchRun $validationRun): NicheCandidate
    {
        $candidate = NicheCandidate::query()->with('discoveryRun')->findOrFail($candidate->id);

        if ($candidate->discoveryRun->user_id !== $user->id || $validationRun->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($validationRun->kind !== ResearchRunKind::DiscoveryValidation) {
            throw new DomainException('A candidate validation link requires a discovery-validation research run.');
        }

        if ($validationRun->market_key !== $candidate->discoveryRun->market_key) {
            throw new DomainException('Candidate validation must use the discovery run market.');
        }

        $candidate->update([
            'validation_research_run_id' => $validationRun->id,
            'status' => NicheCandidateStatus::Validated,
        ]);

        return $candidate->fresh();
    }
}
