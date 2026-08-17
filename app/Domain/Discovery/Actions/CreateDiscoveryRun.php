<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\DiscoverySeedSource;
use App\Domain\Discovery\Services\DeterministicDiscoveryEngine;
use App\Domain\Settings\Actions\FreezeMarketForRequest;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDiscoveryRun
{
    public function __construct(private readonly FreezeMarketForRequest $freezeMarket) {}

    /**
     * @param  list<string>  $seedQueries
     * @param  array<string, string>  $intakeContext
     *
     * @throws AuthorizationException
     */
    public function handle(
        User $user,
        Market $market,
        array $seedQueries,
        ?ResearchProject $project = null,
        int $samplePerSeed = 25,
        int $candidateLimit = 20,
        ?string $submissionToken = null,
        array $intakeContext = [],
    ): DiscoveryRun {
        if ($project !== null && $project->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $seeds = $this->normalizeSeeds($seedQueries);
        if (! in_array($samplePerSeed, [10, 25, 50], true)) {
            throw new DomainException('The per-seed sample must be 10, 25, or 50 videos.');
        }

        if (! in_array($candidateLimit, [10, 20], true)) {
            throw new DomainException('The candidate limit must be 10 or 20.');
        }

        $frozenMarket = ($this->freezeMarket)->handle($market);
        $period = (string) ($intakeContext['period'] ?? 'past_three_months');
        $periodDays = match ($period) {
            'past_week' => 7,
            'past_month' => 30,
            'past_three_months' => 90,
            'past_year' => 365,
            default => null,
        };
        $periodBefore = Date::now()->utc();
        $periodAfter = $periodDays === null ? null : $periodBefore->copy()->subDays($periodDays);

        return DB::transaction(function () use ($user, $market, $project, $seeds, $frozenMarket, $samplePerSeed, $candidateLimit, $submissionToken, $intakeContext, $period, $periodAfter, $periodBefore): DiscoveryRun {
            $run = DiscoveryRun::query()->create([
                'user_id' => $user->id,
                'submission_token' => $submissionToken,
                'research_project_id' => $project?->id,
                'market_id' => $market->id,
                'status' => DiscoveryRunStatus::Draft,
                'market_key' => $frozenMarket->marketKey,
                'region_code' => $frozenMarket->regionCode,
                'relevance_language' => $frozenMarket->relevanceLanguage,
                'parameters' => [
                    'sample_per_seed' => $samplePerSeed,
                    'candidate_limit' => $candidateLimit,
                    'formula_version' => DeterministicDiscoveryEngine::FORMULA_VERSION,
                    'candidate_evidence_thresholds' => DeterministicDiscoveryEngine::THRESHOLDS,
                    'language' => $intakeContext['language'] ?? $frozenMarket->relevanceLanguage,
                    'content_format' => $intakeContext['content_format'] ?? 'any',
                    'period' => $period,
                    'period_after' => $periodAfter?->toIso8601String(),
                    'period_before' => $periodBefore->toIso8601String(),
                    'target_channel_size' => $intakeContext['target_channel_size'] ?? 'any',
                    'channel_size_thresholds' => ['small_max' => 99999, 'mid_size_max' => 999999],
                ],
                'seed_count' => count($seeds),
                'candidate_count' => 0,
                'progress_percent' => 0,
            ]);

            foreach ($seeds as $seed) {
                $run->seeds()->create([
                    'seed_query' => $seed,
                    'source' => DiscoverySeedSource::User,
                ]);
            }

            return $run->load('seeds');
        });
    }

    /**
     * @param  list<string>  $seedQueries
     * @return list<string>
     */
    private function normalizeSeeds(array $seedQueries): array
    {
        $normalized = [];

        foreach ($seedQueries as $seedQuery) {
            $seed = Str::squish($seedQuery);

            if (mb_strlen($seed) < 2 || mb_strlen($seed) > 500) {
                throw new DomainException('Discovery seeds must contain between 2 and 500 characters.');
            }

            $normalized[Str::lower($seed)] ??= $seed;
        }

        if ($normalized === [] || count($normalized) > 10) {
            throw new DomainException('A discovery run requires between 1 and 10 unique seeds.');
        }

        return array_values($normalized);
    }
}
