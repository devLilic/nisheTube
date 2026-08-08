<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Jobs\Discovery\GenerateDiscoveryCandidates;
use App\Models\DiscoveryRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class QueueDiscoveryRun
{
    /** @throws AuthorizationException */
    public function handle(User $user, DiscoveryRun $run): DiscoveryRun
    {
        return DB::transaction(function () use ($user, $run): DiscoveryRun {
            $run = DiscoveryRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if (! in_array($run->status, [DiscoveryRunStatus::Draft, DiscoveryRunStatus::Failed], true)) {
                throw new DomainException('Only draft or failed discovery runs may be queued.');
            }

            if (! $run->seeds()->whereNotNull('research_run_id')->exists()) {
                throw new DomainException('Link at least one completed seed sample before queuing discovery.');
            }

            $run->update([
                'status' => DiscoveryRunStatus::Queued,
                'progress_percent' => 5,
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
            GenerateDiscoveryCandidates::dispatch($run->id)->afterCommit();

            return $run->fresh();
        });
    }
}
