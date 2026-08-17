<?php

namespace App\Domain\Discovery\ReadModels;

use App\Domain\Discovery\Enums\CandidateEvidenceState;
use App\Models\DiscoveryRun;
use App\Models\NicheCandidate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BuildCandidateDecisionTable
{
    private const PER_PAGE = 10;

    /**
     * @param  array{sort: string, direction: 'asc'|'desc', status: string, minimum_score: int, minimum_confidence: int}  $filters
     * @return array<string, mixed>
     */
    public function build(DiscoveryRun $run, array $filters): array
    {
        return [
            'filters' => $filters,
            'candidate_niches' => $this->section($run, CandidateEvidenceState::Candidate, 'candidate_page', $filters),
            'weak_phrase_signals' => $this->section($run, CandidateEvidenceState::WeakPhraseSignal, 'weak_page', $filters),
        ];
    }

    /**
     * @param  array{sort: string, direction: 'asc'|'desc', status: string, minimum_score: int, minimum_confidence: int}  $filters
     * @return array<string, mixed>
     */
    private function section(DiscoveryRun $run, CandidateEvidenceState $state, string $pageName, array $filters): array
    {
        $query = NicheCandidate::query()
            ->where('discovery_run_id', $run->id)
            ->with('validationResearchRun');

        if ($state === CandidateEvidenceState::Candidate) {
            $query->whereIn('evidence_state', [
                CandidateEvidenceState::Candidate->value,
                CandidateEvidenceState::Legacy->value,
            ]);
        } else {
            $query->where('evidence_state', $state->value);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['minimum_score'] > 0) {
            $query->where('overall_score', '>=', $filters['minimum_score']);
        }

        if ($filters['minimum_confidence'] > 0) {
            $query->where('confidence_score', '>=', $filters['minimum_confidence']);
        }

        $this->sort($query, $filters['sort'], $filters['direction']);

        $page = $query->paginate(self::PER_PAGE, ['*'], $pageName)->withQueryString();

        return $this->serializePage($page);
    }

    /**
     * @param  Builder<NicheCandidate>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function sort(Builder $query, string $sort, string $direction): void
    {
        $columns = [
            'evidence_score' => 'overall_score',
            'confidence' => 'confidence_score',
            'status' => 'status',
            'theme' => 'phrase',
        ];
        $jsonPaths = [
            'videos' => '$.source_video_count',
            'channels' => '$.unique_channel_count',
            'small_channel_proof' => '$.small_channel_proof_count',
            'typical_performance' => '$.typical_median_views_per_day',
            'stability' => '$.stability_score',
        ];

        if (isset($columns[$sort])) {
            $column = $columns[$sort];
            $query->orderByRaw("CASE WHEN {$column} IS NULL THEN 1 ELSE 0 END")
                ->orderBy($column, $direction);
        } else {
            $path = $jsonPaths[$sort];
            $expression = "CAST(json_extract(evidence, '{$path}') AS DECIMAL(14,4))";
            $query->orderByRaw("CASE WHEN {$expression} IS NULL THEN 1 ELSE 0 END")
                ->orderBy(DB::raw($expression), $direction);
        }

        if ($sort !== 'theme') {
            $query->orderBy('phrase');
        }

        $query->orderBy('id');
    }

    /**
     * @param  LengthAwarePaginator<int, NicheCandidate>  $page
     * @return array<string, mixed>
     */
    private function serializePage(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())->map(fn (NicheCandidate $candidate): array => $this->candidate($candidate))->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
        ];
    }

    /** @return array<string, mixed> */
    private function candidate(NicheCandidate $candidate): array
    {
        return [
            'public_id' => $candidate->public_id,
            'phrase' => $candidate->phrase,
            'summary' => $candidate->summary,
            'status' => $candidate->status->value,
            'overall_score' => $candidate->overall_score,
            'confidence_score' => $candidate->confidence_score,
            'formula_version' => $candidate->formula_version,
            'evidence_state' => $candidate->evidence_state->value,
            'evidence' => $candidate->evidence,
            'validation_run' => $candidate->validationResearchRun === null ? null : [
                'public_id' => $candidate->validationResearchRun->public_id,
                'status' => $candidate->validationResearchRun->status->value,
            ],
        ];
    }
}
