<?php

namespace App\Domain\Discovery\Actions;

use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartDiscoveryRun
{
    public function __construct(
        private readonly CreateDiscoveryRun $createRun,
        private readonly LinkDiscoverySeedResearchRun $linkSeed,
        private readonly QueueDiscoveryRun $queueRun,
    ) {}

    /**
     * @param  list<array{query: string, research_run: ResearchRun}>  $seeds
     * @param  array<string, string>  $intakeContext
     */
    public function handle(
        User $user,
        Market $market,
        array $seeds,
        int $samplePerSeed,
        int $candidateLimit,
        ?string $submissionToken = null,
        array $intakeContext = [],
    ): DiscoveryRun {
        return DB::transaction(function () use ($user, $market, $seeds, $samplePerSeed, $candidateLimit, $submissionToken, $intakeContext): DiscoveryRun {
            $run = $this->createRun->handle(
                user: $user,
                market: $market,
                seedQueries: array_map(fn (array $seed): string => $seed['query'], $seeds),
                samplePerSeed: $samplePerSeed,
                candidateLimit: $candidateLimit,
                submissionToken: $submissionToken,
                intakeContext: $intakeContext,
            );

            foreach ($run->seeds as $index => $seed) {
                $this->linkSeed->handle($user, $seed, $seeds[$index]['research_run']);
            }

            return $this->queueRun->handle($user, $run);
        });
    }
}
