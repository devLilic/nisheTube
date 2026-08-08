<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\ResearchRun;
use Throwable;

class FailResearchRun
{
    public function __construct(private readonly TransitionResearchRun $transition) {}

    public function handle(int $researchRunId, Throwable $exception): ?ResearchRun
    {
        $run = ResearchRun::query()->find($researchRunId);

        if ($run === null || $run->status->isTerminal()) {
            return $run;
        }

        if (! in_array($run->status, [
            ResearchRunStatus::Queued,
            ResearchRunStatus::Searching,
            ResearchRunStatus::Enriching,
        ], true)) {
            return $run;
        }

        $failure = $exception instanceof YouTubeProviderException
            ? new RunFailure($exception->providerCode->value, $exception->providerCode->safeMessage())
            : new RunFailure(
                'research_collection_failed',
                'The research collection could not be completed. Retry the run or review the integration settings.',
            );

        return $this->transition->handle($run, ResearchRunStatus::Failed, $failure);
    }
}
