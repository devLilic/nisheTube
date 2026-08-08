<?php

namespace App\Jobs\Retention;

use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Models\CleanupRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Date;
use Throwable;

final class ExecuteCleanupRun implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $cleanupRunId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("cleanup-run:{$this->cleanupRunId}"))
                ->releaseAfter(5)
                ->expireAfter(240),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->cleanupRunId;
    }

    public function handle(ExecuteCleanup $cleanup): void
    {
        $run = CleanupRun::query()->with('user')->find($this->cleanupRunId);

        if ($run === null) {
            return;
        }

        $cleanup->handle($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = CleanupRun::query()->find($this->cleanupRunId);

        if ($run === null || $run->status === CleanupStatus::Completed) {
            return;
        }

        $run->update([
            'status' => CleanupStatus::Failed,
            'failed_at' => Date::now(),
            'error_code' => 'retention_cleanup_failed',
            'error_message' => 'Cleanup could not finish. Saved records were preserved where deletion did not complete, and this cleanup can be retried safely.',
        ]);
    }
}
