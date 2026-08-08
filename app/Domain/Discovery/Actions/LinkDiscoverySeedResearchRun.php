<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\DiscoverySeed;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class LinkDiscoverySeedResearchRun
{
    /** @throws AuthorizationException */
    public function handle(User $user, DiscoverySeed $seed, ResearchRun $researchRun): DiscoverySeed
    {
        $seed = DiscoverySeed::query()->with('discoveryRun')->findOrFail($seed->id);

        if ($seed->discoveryRun->user_id !== $user->id || $researchRun->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($seed->discoveryRun->status !== DiscoveryRunStatus::Draft) {
            throw new DomainException('Discovery seed samples can only be linked before the discovery run is queued.');
        }

        if ($researchRun->status !== ResearchRunStatus::Completed) {
            throw new DomainException('A discovery seed requires a completed research sample.');
        }

        if ($researchRun->market_key !== $seed->discoveryRun->market_key) {
            throw new DomainException('Discovery seed samples must use the discovery run market.');
        }

        $seed->update(['research_run_id' => $researchRun->id]);

        return $seed->fresh();
    }
}
