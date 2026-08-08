<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\NicheCandidate;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateCandidateStatus
{
    /** @throws AuthorizationException */
    public function handle(User $user, NicheCandidate $candidate, NicheCandidateStatus $status): NicheCandidate
    {
        $candidate = NicheCandidate::query()->with('discoveryRun')->findOrFail($candidate->id);

        if ($candidate->discoveryRun->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($candidate->status === NicheCandidateStatus::Validated) {
            throw new DomainException('Validated candidates cannot be reclassified.');
        }

        if ($status === NicheCandidateStatus::Validated) {
            throw new DomainException('Use a validation research run to validate a candidate.');
        }

        $candidate->update(['status' => $status]);

        return $candidate->fresh();
    }
}
