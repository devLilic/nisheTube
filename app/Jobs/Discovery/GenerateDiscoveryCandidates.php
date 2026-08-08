<?php

namespace App\Jobs\Discovery;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Discovery\ReadModels\CollectDiscoveryObservations;
use App\Domain\Discovery\Services\DeterministicDiscoveryEngine;
use App\Models\DiscoveryRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateDiscoveryCandidates implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $discoveryRunId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function uniqueId(): string
    {
        return (string) $this->discoveryRunId;
    }

    public function handle(
        CollectDiscoveryObservations $observations,
        DeterministicDiscoveryEngine $engine,
    ): void {
        $run = DiscoveryRun::query()->find($this->discoveryRunId);

        if ($run === null || $run->status === DiscoveryRunStatus::Completed) {
            return;
        }

        if ($run->status === DiscoveryRunStatus::Queued) {
            $run->update([
                'status' => DiscoveryRunStatus::Analyzing,
                'progress_percent' => 25,
                'started_at' => $run->started_at ?? Date::now(),
            ]);
        } elseif ($run->status !== DiscoveryRunStatus::Analyzing) {
            return;
        }

        $candidateLimit = (int) ($run->parameters['candidate_limit'] ?? 20);
        $drafts = $engine->generate($observations->handle($run), $candidateLimit);

        DB::transaction(function () use ($run, $drafts): void {
            foreach ($drafts as $draft) {
                $candidate = $run->candidates()->firstOrNew(['phrase' => $draft->phrase]);

                if (! $candidate->exists) {
                    $candidate->status = NicheCandidateStatus::New;
                }

                $candidate->fill([
                    'cluster_key' => $draft->clusterKey,
                    'summary' => $draft->summary,
                    'evidence' => $draft->evidence,
                    'overall_score' => $draft->overallScore,
                    'confidence_score' => $draft->confidenceScore,
                    'formula_version' => $draft->formulaVersion,
                ])->save();
            }

            $run->update([
                'status' => DiscoveryRunStatus::Completed,
                'candidate_count' => $run->candidates()->count(),
                'progress_percent' => 100,
                'completed_at' => Date::now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $run = DiscoveryRun::query()->find($this->discoveryRunId);

        if ($run === null || $run->status === DiscoveryRunStatus::Completed) {
            return;
        }

        $run->update([
            'status' => DiscoveryRunStatus::Failed,
            'failed_at' => Date::now(),
            'error_code' => 'discovery_generation_failed',
            'error_message' => 'Discovery candidate generation failed. The saved seed samples can be retried.',
        ]);
    }
}
