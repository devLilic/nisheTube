<?php

namespace App\Domain\Dashboard\ReadModels;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Scoring\Services\NicheOpportunityV1;
use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final class BuildDashboard
{
    private const RECENT_RUN_LIMIT = 8;

    private const TOP_OPPORTUNITY_LIMIT = 5;

    private const TREND_POINT_LIMIT = 12;

    private const OPPORTUNITY_WINDOW_DAYS = 90;

    public function __construct(private readonly QuotaLedger $quotaLedger) {}

    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $now = CarbonImmutable::instance(Date::now())->utc();
        $monthStartsAt = $now->setTimezone($user->timezone)->startOfMonth()->utc();
        $opportunityStartsAt = $now->subDays(self::OPPORTUNITY_WINDOW_DAYS);
        $cleanupCutoff = $now->subMonthsNoOverflow(6);
        $topOpportunities = $this->topOpportunities($user, $opportunityStartsAt);

        return [
            'generated_at' => $now->toIso8601String(),
            'counts' => [
                ...$this->runCounts($user, $monthStartsAt),
                'saved_projects' => $this->savedProjectCount($user),
                'saved_items' => null,
            ],
            'availability' => [
                'saved_items' => false,
                'discovery_candidates' => false,
            ],
            'best_opportunity' => $topOpportunities[0] ?? null,
            'recent_runs' => $this->recentRuns($user),
            'top_opportunities' => $topOpportunities,
            'opportunity_window_days' => self::OPPORTUNITY_WINDOW_DAYS,
            'score_trend' => $this->scoreTrend($user),
            'quota' => $this->quotaLedger->summary($now)->toSafeArray(),
            'cleanup' => $this->cleanupStatus($user, $cleanupCutoff),
        ];
    }

    /** @return array{research_runs_this_month: int, active_runs: int, failed_runs_this_month: int} */
    private function runCounts(User $user, CarbonImmutable $monthStartsAt): array
    {
        $activeStatuses = [
            ResearchRunStatus::Draft->value,
            ResearchRunStatus::Queued->value,
            ResearchRunStatus::Searching->value,
            ResearchRunStatus::Enriching->value,
            ResearchRunStatus::Scoring->value,
        ];
        $activePlaceholders = implode(', ', array_fill(0, count($activeStatuses), '?'));
        $bindings = [
            $monthStartsAt,
            ...$activeStatuses,
            ResearchRunStatus::Failed->value,
            $monthStartsAt,
        ];

        $record = DB::table('research_runs')
            ->where('user_id', $user->id)
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as research_runs_this_month', [$bindings[0]])
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$activePlaceholders}) THEN 1 ELSE 0 END) as active_runs",
                array_slice($bindings, 1, count($activeStatuses)),
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND failed_at >= ? THEN 1 ELSE 0 END) as failed_runs_this_month',
                array_slice($bindings, count($activeStatuses) + 1),
            )
            ->first();

        return [
            'research_runs_this_month' => (int) ($record->research_runs_this_month ?? 0),
            'active_runs' => (int) ($record->active_runs ?? 0),
            'failed_runs_this_month' => (int) ($record->failed_runs_this_month ?? 0),
        ];
    }

    private function savedProjectCount(User $user): int
    {
        return DB::table('research_projects')
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function recentRuns(User $user): array
    {
        $formulaVersion = $this->formulaVersion();

        return array_values(DB::table('research_runs as run')
            ->leftJoin('opportunity_scores as score', function ($join) use ($formulaVersion): void {
                $join->on('score.research_run_id', '=', 'run.id')
                    ->where('score.formula_version', '=', $formulaVersion);
            })
            ->where('run.user_id', $user->id)
            ->orderByDesc('run.created_at')
            ->orderByDesc('run.id')
            ->limit(self::RECENT_RUN_LIMIT)
            ->get([
                'run.public_id',
                'run.query_text',
                'run.market_key',
                'run.status',
                'run.progress_percent',
                'run.collected_result_count',
                'run.enriched_result_count',
                'run.collection_warnings',
                'run.error_code',
                'run.error_message',
                'run.created_at',
                'run.completed_at',
                'run.failed_at',
                'score.overall_score',
                'score.confidence_score',
                'score.formula_version',
                'score.calculated_at',
            ])
            ->map(fn (stdClass $record): array => [
                'public_id' => (string) $record->public_id,
                'query_text' => (string) $record->query_text,
                'market_key' => (string) $record->market_key,
                'status' => (string) $record->status,
                'progress_percent' => (int) $record->progress_percent,
                'collected_result_count' => (int) $record->collected_result_count,
                'enriched_result_count' => (int) $record->enriched_result_count,
                'has_partial_data' => $this->warningCount($record->collection_warnings) > 0,
                'error_code' => $record->error_code !== null ? (string) $record->error_code : null,
                'error_message' => $record->error_message !== null ? (string) $record->error_message : null,
                'created_at' => $this->timestamp($record->created_at),
                'completed_at' => $this->timestamp($record->completed_at),
                'failed_at' => $this->timestamp($record->failed_at),
                'score' => $this->score($record),
            ])
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function topOpportunities(User $user, CarbonImmutable $startsAt): array
    {
        return array_values(DB::table('opportunity_scores as score')
            ->join('research_runs as run', 'run.id', '=', 'score.research_run_id')
            ->where('run.user_id', $user->id)
            ->where('run.status', ResearchRunStatus::Completed->value)
            ->where('score.formula_version', $this->formulaVersion())
            ->where('score.calculated_at', '>=', $startsAt)
            ->orderByDesc('score.overall_score')
            ->orderByDesc('score.calculated_at')
            ->limit(self::TOP_OPPORTUNITY_LIMIT)
            ->get([
                'run.public_id',
                'run.query_text',
                'run.market_key',
                'run.completed_at',
                'score.overall_score',
                'score.confidence_score',
                'score.formula_version',
                'score.sample_size',
                'score.calculated_at',
            ])
            ->map(fn (stdClass $record): array => [
                'public_id' => (string) $record->public_id,
                'query_text' => (string) $record->query_text,
                'market_key' => (string) $record->market_key,
                'overall_score' => (float) $record->overall_score,
                'confidence_score' => (float) $record->confidence_score,
                'formula_version' => (string) $record->formula_version,
                'sample_size' => (int) $record->sample_size,
                'completed_at' => $this->timestamp($record->completed_at),
                'calculated_at' => $this->timestamp($record->calculated_at),
            ])
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function scoreTrend(User $user): array
    {
        /** @var Collection<int, stdClass> $records */
        $records = DB::table('opportunity_scores as score')
            ->join('research_runs as run', 'run.id', '=', 'score.research_run_id')
            ->where('run.user_id', $user->id)
            ->where('run.status', ResearchRunStatus::Completed->value)
            ->where('score.formula_version', $this->formulaVersion())
            ->orderByDesc('score.calculated_at')
            ->orderByDesc('score.id')
            ->limit(self::TREND_POINT_LIMIT)
            ->get([
                'run.public_id',
                'run.query_text',
                'run.market_key',
                'score.overall_score',
                'score.confidence_score',
                'score.formula_version',
                'score.calculated_at',
            ]);

        return array_values($records
            ->reverse()
            ->values()
            ->map(fn (stdClass $record): array => [
                'public_id' => (string) $record->public_id,
                'query_text' => (string) $record->query_text,
                'market_key' => (string) $record->market_key,
                'overall_score' => (float) $record->overall_score,
                'confidence_score' => (float) $record->confidence_score,
                'formula_version' => (string) $record->formula_version,
                'calculated_at' => $this->timestamp($record->calculated_at),
            ])
            ->all());
    }

    /** @return array{status: string, cutoff_at: string, candidate_run_count: int, oldest_candidate_at: ?string} */
    private function cleanupStatus(User $user, CarbonImmutable $cutoff): array
    {
        $record = DB::table('research_runs')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where(function ($completed) use ($cutoff): void {
                        $completed
                            ->where('status', ResearchRunStatus::Completed->value)
                            ->where('completed_at', '<', $cutoff);
                    })
                    ->orWhere(function ($failed) use ($cutoff): void {
                        $failed
                            ->where('status', ResearchRunStatus::Failed->value)
                            ->where('failed_at', '<', $cutoff);
                    });
            })
            ->selectRaw('COUNT(*) as candidate_run_count')
            ->selectRaw('MIN(COALESCE(completed_at, failed_at)) as oldest_candidate_at')
            ->first();
        $candidateCount = (int) ($record->candidate_run_count ?? 0);

        return [
            'status' => $candidateCount > 0 ? 'due' : 'current',
            'cutoff_at' => $cutoff->toIso8601String(),
            'candidate_run_count' => $candidateCount,
            'oldest_candidate_at' => $this->timestamp($record->oldest_candidate_at ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private function score(stdClass $record): ?array
    {
        if ($record->overall_score === null) {
            return null;
        }

        return [
            'overall_score' => (float) $record->overall_score,
            'confidence_score' => (float) $record->confidence_score,
            'formula_version' => (string) $record->formula_version,
            'calculated_at' => $this->timestamp($record->calculated_at),
        ];
    }

    private function formulaVersion(): string
    {
        $configured = config('scoring.default_version');

        return is_string($configured) && $configured !== ''
            ? $configured
            : NicheOpportunityV1::VERSION;
    }

    private function warningCount(mixed $warnings): int
    {
        if (is_array($warnings)) {
            return count($warnings);
        }

        if (! is_string($warnings)) {
            return 0;
        }

        $decoded = json_decode($warnings, true);

        return is_array($decoded) ? count($decoded) : 0;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::parse((string) $value, 'UTC')->utc()->toIso8601String();
    }
}
