<?php

namespace App\Domain\Scoring\Actions;

use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Scoring\Services\NicheOpportunityV1;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use DomainException;
use Illuminate\Support\Facades\DB;

class CalculateOpportunityScore
{
    public function __construct(
        private readonly BuildScoringInput $buildInput,
        private readonly NicheOpportunityV1 $engine,
        private readonly TransitionResearchRun $transition,
    ) {}

    public function handle(ResearchRun $run): OpportunityScore
    {
        $existing = $run->opportunityScores()
            ->where('formula_version', NicheOpportunityV1::VERSION)
            ->first();

        if ($existing !== null) {
            $this->completeIfNecessary($run);

            return $existing;
        }

        if ($run->status !== ResearchRunStatus::Scoring) {
            throw new DomainException('Only a research run in scoring may receive an opportunity score.');
        }

        $result = $this->engine->calculate($this->buildInput->handle($run));

        $score = DB::transaction(function () use ($run, $result): OpportunityScore {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);
            $existing = $lockedRun->opportunityScores()
                ->where('formula_version', $result->formulaVersion)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($lockedRun->status !== ResearchRunStatus::Scoring) {
                throw new DomainException('The research run left scoring before its score could be persisted.');
            }

            return $lockedRun->opportunityScores()->create([
                ...$result->persistenceAttributes(),
                'calculated_at' => now(),
            ]);
        });

        $this->completeIfNecessary($run->fresh() ?? $run);

        return $score;
    }

    private function completeIfNecessary(ResearchRun $run): void
    {
        if ($run->status === ResearchRunStatus::Scoring) {
            $this->transition->handle($run, ResearchRunStatus::Completed);
        }
    }
}
