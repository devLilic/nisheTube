<?php

namespace App\Http\ViewModels;

use App\Domain\Catalog\ReadModels\BuildResearchRunAnalysis;
use App\Domain\Collection\ReadModels\BuildResearchRunProvenance;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\ReadModels\BuildResearchDecisionSummary;
use App\Domain\Research\ReadModels\BuildResearchEvidenceInspection;
use App\Domain\Research\ReadModels\BuildResearchEvidenceProfile;
use App\Domain\Settings\Enums\MarketKey;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;

class ResearchRunViewModel
{
    public function __construct(
        private readonly BuildResearchRunAnalysis $buildAnalysis,
        private readonly BuildResearchRunProvenance $buildProvenance,
        private readonly BuildResearchDecisionSummary $buildDecisionSummary,
        private readonly BuildResearchEvidenceInspection $buildEvidenceInspection,
        private readonly BuildResearchEvidenceProfile $buildEvidenceProfile,
    ) {}

    /**
     * @param  array{sort: string, direction: string, filter: string, page: int}|null  $evidenceQuery
     * @return array<string, mixed>
     */
    public function toArray(ResearchRun $run, bool $withResults = true, ?array $evidenceQuery = null): array
    {
        $data = [
            'public_id' => $run->public_id,
            'query_text' => $run->query_text,
            'market' => [
                'key' => $run->market_key,
                'name' => MarketKey::tryFrom($run->market_key)?->label() ?? $run->market_key,
            ],
            'status' => $run->status->value,
            'attempt_number' => $run->attempt_number,
            'requested_result_count' => $run->requested_result_count,
            'collected_result_count' => $run->collected_result_count,
            'enriched_result_count' => $run->enriched_result_count,
            'progress_percent' => $run->progress_percent,
            'collection_warnings' => $run->collection_warnings ?? [],
            'parameters' => [
                'search_order' => $run->parameters['search_order'] ?? 'relevance',
                'published_after' => $run->parameters['published_after'] ?? null,
                'published_before' => $run->parameters['published_before'] ?? null,
                'video_duration' => $run->parameters['video_duration'] ?? null,
                'video_category_id' => $run->parameters['video_category_id'] ?? null,
                'workflow_mode' => $run->parameters['workflow_mode'] ?? 'validate_idea',
                'preset_key' => $run->parameters['preset_key'] ?? 'custom',
                'language' => $run->parameters['language'] ?? $run->relevance_language,
                'content_format' => $run->parameters['content_format'] ?? 'any',
                'target_channel_size' => $run->parameters['target_channel_size'] ?? 'any',
            ],
            'created_at' => $run->created_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'search_completed_at' => $run->search_completed_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'is_active' => ! $run->status->isTerminal(),
            'can_retry' => $run->status === ResearchRunStatus::Failed,
            'error' => $this->errorGuidance($run),
        ];

        if ($withResults) {
            $score = $this->score($run);
            $analysis = $this->buildAnalysis->handle($run);
            $data['score'] = $score;
            $data['profitability_fit'] = $this->profitabilityFit($run);
            $data['collection'] = [
                'pages_collected' => $run->searchPages()->count(),
                'sample_results' => $run->searchResults()
                    ->orderBy('result_rank')
                    ->limit(6)
                    ->get(['provider_video_id', 'title', 'provider_channel_id', 'published_at', 'result_rank'])
                    ->map(fn ($result): array => [
                        'provider_video_id' => $result->provider_video_id,
                        'title' => $result->title,
                        'provider_channel_id' => $result->provider_channel_id,
                        'published_at' => $result->published_at->toIso8601String(),
                        'result_rank' => $result->result_rank,
                    ])->all(),
            ];
            $data['analysis'] = $analysis;
            $data['evidence_profile'] = $this->buildEvidenceProfile->handle($run);
            $data['evidence_inspection'] = $this->buildEvidenceInspection->handle($run, $evidenceQuery ?? [
                'sort' => 'relevance',
                'direction' => 'desc',
                'filter' => 'all',
                'page' => 1,
            ]);
            $data['decision_summary'] = $this->buildDecisionSummary->handle($run, $score, $analysis, $data['evidence_profile']);
            $data['provenance'] = $this->buildProvenance->handle($run);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function score(ResearchRun $run): ?array
    {
        $score = $run->opportunityScores()
            ->latest('calculated_at')
            ->latest('id')
            ->first();

        if ($score === null) {
            return null;
        }

        $weights = $score->input_summary['configuration']['weights'] ?? [];

        $labels = $score->input_summary['component_labels'] ?? [];

        return [
            'overall_score' => (float) $score->overall_score,
            'overall_label' => $this->opportunityLabel((float) $score->overall_score),
            'confidence_score' => (float) $score->confidence_score,
            'confidence_label' => $this->confidenceLabel((float) $score->confidence_score),
            'formula_version' => $score->formula_version,
            'sample_size' => $score->sample_size,
            'sample_views' => [
                'full_sample_count' => $score->input_summary['sample_views']['full_sample_count'] ?? $score->sample_size,
                'strict_sample_count' => $score->input_summary['sample_views']['strict_sample_count'] ?? null,
            ],
            'calculated_at' => $score->calculated_at->toIso8601String(),
            'components' => [
                $this->component($score, $weights, 'demand_momentum', $labels['demand_momentum'] ?? 'Demand momentum', 'demand_momentum_score'),
                $this->component($score, $weights, 'competition_opportunity', $labels['competition_opportunity'] ?? 'Competition opportunity', 'competition_opportunity_score'),
                $this->component($score, $weights, 'audience_reachability', $labels['audience_reachability'] ?? 'Audience reachability', 'audience_reachability_score'),
                $this->component($score, $weights, 'content_freshness_gap', $labels['content_freshness_gap'] ?? 'Content freshness gap', 'content_freshness_gap_score'),
                $this->component($score, $weights, 'creator_viability', $labels['creator_viability'] ?? 'Creator viability', 'creator_viability_score'),
            ],
            'warnings' => $score->warnings,
        ];
    }

    /** @return array<string, mixed>|null */
    private function profitabilityFit(ResearchRun $run): ?array
    {
        $fit = $run->profitabilityFitScores()->latest('calculated_at')->latest('id')->first();

        if ($fit === null) {
            return null;
        }

        return [
            'fit_score' => (float) $fit->fit_score,
            'confidence_score' => (float) $fit->confidence_score,
            'formula_version' => $fit->formula_version,
            'calculated_at' => $fit->calculated_at->toIso8601String(),
            'source_formula_version' => $fit->input_summary['source']['formula_version'] ?? 'Unavailable',
            'explanations' => $fit->explanations,
            'warnings' => $fit->warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array{key: string, label: string, score: float, weight_percent: float, explanation: string}
     */
    private function component(
        OpportunityScore $score,
        array $weights,
        string $key,
        string $label,
        string $scoreAttribute,
    ): array {
        $weight = $weights[$key] ?? 0;
        $explanation = $score->explanations[$key] ?? 'No explanation was stored for this component.';

        return [
            'key' => $key,
            'label' => $label,
            'score' => (float) $score->getAttribute($scoreAttribute),
            'weight_percent' => is_numeric($weight) ? ((float) $weight) * 100 : 0.0,
            'explanation' => $explanation,
        ];
    }

    private function opportunityLabel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Strong opportunity',
            $score >= 65 => 'Promising',
            $score >= 50 => 'Mixed',
            $score >= 35 => 'Competitive / uncertain',
            default => 'Weak observed opportunity',
        };
    }

    private function confidenceLabel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'High confidence',
            $score >= 60 => 'Moderate confidence',
            $score >= 40 => 'Limited confidence',
            default => 'Exploratory only',
        };
    }

    /** @return array{code: string, title: string, message: string, guidance: string, action: 'settings'|'new_search'|'retry'}|null */
    private function errorGuidance(ResearchRun $run): ?array
    {
        if ($run->status !== ResearchRunStatus::Failed || $run->error_code === null) {
            return null;
        }

        [$title, $guidance, $action] = match ($run->error_code) {
            'youtube_key_missing' => [
                'YouTube API key is missing',
                'Add the server-side key in the local environment, then retry this saved run.',
                'settings',
            ],
            'youtube_key_invalid', 'youtube_api_disabled' => [
                'YouTube connection needs attention',
                'Review the local integration settings before retrying this saved run.',
                'settings',
            ],
            'youtube_quota_exhausted' => [
                'Search quota is exhausted',
                'Wait for the Pacific Time reset shown in the quota panel, then retry. Google Cloud Console is authoritative.',
                'settings',
            ],
            'youtube_request_invalid' => [
                'YouTube rejected these filters',
                'Create a new search with adjusted filters. This failed attempt remains unchanged for history.',
                'new_search',
            ],
            'youtube_rate_limited' => [
                'YouTube is temporarily rate limiting requests',
                'Wait briefly and retry this saved run.',
                'retry',
            ],
            'research_scoring_failed' => [
                'Opportunity scoring could not finish',
                'The saved metrics remain available. Retry to create a new immutable attempt and calculate its score.',
                'retry',
            ],
            default => [
                'Collection could not finish',
                'The saved run is safe. Check the connection and retry when the provider is available.',
                'retry',
            ],
        };

        return [
            'code' => $run->error_code,
            'title' => $title,
            'message' => $run->error_message ?? 'The research collection could not be completed.',
            'guidance' => $guidance,
            'action' => $action,
        ];
    }
}
