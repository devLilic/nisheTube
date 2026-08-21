<?php

namespace App\Domain\Research\ReadModels;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;

class BuildResearchJobControl
{
    private const WORKER_START_GRACE_SECONDS = 120;

    /** @return array{state: 'not_applicable'|'waiting'|'worker_unavailable', description: string, can_cancel: bool} */
    public function handle(ResearchRun $run): array
    {
        if ($run->status !== ResearchRunStatus::Queued) {
            return [
                'state' => 'not_applicable',
                'description' => '',
                'can_cancel' => false,
            ];
        }

        $waitingSeconds = $run->created_at?->diffInSeconds(now()) ?? 0;

        if ($waitingSeconds >= self::WORKER_START_GRACE_SECONDS) {
            return [
                'state' => 'worker_unavailable',
                'description' => 'This run has waited more than two minutes without starting. The local queue worker may not be running. Starting a worker can continue this same run; it will not create a second YouTube request.',
                'can_cancel' => true,
            ];
        }

        return [
            'state' => 'waiting',
            'description' => 'The local queue worker has not started this run yet. Estimated completion is unavailable until collection begins.',
            'can_cancel' => true,
        ];
    }
}
