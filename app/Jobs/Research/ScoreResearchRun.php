<?php

namespace App\Jobs\Research;

use App\Domain\Research\Actions\FailResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Scoring\Actions\CalculateOpportunityScore;
use App\Models\ResearchRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ScoreResearchRun implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $researchRunId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("research-run:{$this->researchRunId}:scoring"))
                ->releaseAfter(5)
                ->expireAfter(180),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->researchRunId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5];
    }

    public function handle(CalculateOpportunityScore $calculate): void
    {
        $run = ResearchRun::query()->find($this->researchRunId);

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        if ($run->status !== ResearchRunStatus::Scoring) {
            return;
        }

        $calculate->handle($run);
    }

    public function failed(?Throwable $exception): void
    {
        app(FailResearchRun::class)->handle(
            $this->researchRunId,
            $exception ?? new \RuntimeException('The scoring job failed.'),
        );
    }
}
