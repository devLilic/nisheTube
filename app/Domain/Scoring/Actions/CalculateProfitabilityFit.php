<?php

namespace App\Domain\Scoring\Actions;

use App\Models\OpportunityScore;
use App\Models\ProfitabilityFitScore;
use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;

class CalculateProfitabilityFit
{
    public const VERSION = 'profitability-fit-v1';

    public function handle(ResearchRun $run, OpportunityScore $opportunity): ProfitabilityFitScore
    {
        $existing = $run->profitabilityFitScores()
            ->where('formula_version', self::VERSION)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $inputs = [
            'observed_activity' => (float) $opportunity->demand_momentum_score,
            'audience_reachability' => (float) $opportunity->audience_reachability_score,
            'creator_viability' => (float) $opportunity->creator_viability_score,
            'opportunity_confidence' => (float) $opportunity->confidence_score,
        ];
        $fit = round(($inputs['observed_activity'] * 0.30) + ($inputs['audience_reachability'] * 0.30) + ($inputs['creator_viability'] * 0.25) + ($inputs['opportunity_confidence'] * 0.15), 4);
        $warnings = [[
            'code' => 'estimated_not_revenue',
            'message' => 'This is an estimated fit signal from stored observed activity and creator evidence. It does not measure revenue, RPM, costs, or profit.',
        ]];
        if ($inputs['opportunity_confidence'] < 60) {
            $warnings[] = ['code' => 'limited_evidence_confidence', 'message' => 'The underlying opportunity evidence has limited confidence, so treat this estimate as directional.'];
        }

        return DB::transaction(function () use ($run, $opportunity, $inputs, $fit, $warnings): ProfitabilityFitScore {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);
            $existing = $lockedRun->profitabilityFitScores()->where('formula_version', self::VERSION)->first();

            if ($existing !== null) {
                return $existing;
            }

            return $lockedRun->profitabilityFitScores()->create([
                'opportunity_score_id' => $opportunity->id,
                'formula_version' => self::VERSION,
                'fit_score' => number_format($fit, 4, '.', ''),
                'confidence_score' => number_format($inputs['opportunity_confidence'], 4, '.', ''),
                'input_summary' => [
                    'source' => ['opportunity_score_id' => $opportunity->id, 'formula_version' => $opportunity->formula_version, 'calculated_at' => $opportunity->calculated_at->toIso8601String()],
                    'weights' => ['observed_activity' => 0.30, 'audience_reachability' => 0.30, 'creator_viability' => 0.25, 'opportunity_confidence' => 0.15],
                    'inputs' => $inputs,
                ],
                'explanations' => [
                    'fit' => 'The fit estimate combines stored observed activity, audience reachability, creator viability, and evidence confidence. It intentionally excludes revenue, CPM/RPM, costs, and profit because those values are not measured here.',
                    'confidence' => 'Confidence is pinned from the source opportunity score; missing or partial source evidence remains visible rather than being filled in.',
                ],
                'warnings' => $warnings,
                'calculated_at' => now(),
            ]);
        });
    }
}
