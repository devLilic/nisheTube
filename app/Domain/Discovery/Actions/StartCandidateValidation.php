<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\QueueResearchRun;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class StartCandidateValidation
{
    public function __construct(
        private readonly CreateResearchQuery $createQuery,
        private readonly CreateResearchRun $createRun,
        private readonly QueueResearchRun $queueRun,
        private readonly LinkCandidateValidationRun $linkCandidate,
    ) {}

    /** @throws AuthorizationException */
    public function handle(User $user, NicheCandidate $candidate, int $requestedResultCount): ResearchRun
    {
        return DB::transaction(function () use ($user, $candidate, $requestedResultCount): ResearchRun {
            $candidate = NicheCandidate::query()
                ->with('discoveryRun.market')
                ->findOrFail($candidate->id);

            if ($candidate->discoveryRun->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if ($candidate->validation_research_run_id !== null) {
                throw new DomainException('This candidate already has a validation research run.');
            }

            $query = $this->createQuery->handle(
                user: $user,
                market: $candidate->discoveryRun->market,
                queryText: $candidate->phrase,
            );
            $run = $this->createRun->handle(
                user: $user,
                query: $query,
                requestedResultCount: $requestedResultCount,
                kind: ResearchRunKind::DiscoveryValidation,
            );
            $run = $this->queueRun->handle($user, $run);
            $this->linkCandidate->handle($user, $candidate, $run);

            return $run;
        });
    }
}
