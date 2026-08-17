<?php

namespace App\Domain\Research\ReadModels;

use App\Domain\Scoring\Services\ResearchEvidenceV1;
use App\Models\ResearchEvidenceProfile;
use App\Models\ResearchRun;

class BuildResearchEvidenceProfile
{
    /** @return array<string, mixed> */
    public function handle(ResearchRun $run): array
    {
        $profile = $run->evidenceProfiles()
            ->where('evidence_version', ResearchEvidenceV1::VERSION)
            ->first();

        if ($profile === null) {
            return [
                'state' => $run->status->isTerminal() ? 'not_calculated' : 'pending',
                'version' => null,
                'description' => $run->status->isTerminal()
                    ? 'This historical run predates versioned relevance, format, outlier, and stability evidence.'
                    : 'Evidence will be frozen during the scoring stage after enrichment finishes.',
                'full_sample_count' => 0,
                'strict_sample_count' => 0,
                'sample_evidence' => null,
                'format_evidence' => null,
                'outlier_evidence' => null,
                'stability' => [
                    'state' => 'unavailable', 'label' => null,
                    'reason' => 'No versioned evidence profile is stored for this run.',
                ],
                'warnings' => [],
                'calculated_at' => null,
            ];
        }

        return $this->profile($profile);
    }

    /** @return array<string, mixed> */
    private function profile(ResearchEvidenceProfile $profile): array
    {
        return [
            'state' => 'available',
            'version' => $profile->evidence_version,
            'normalization_version' => $profile->normalization_version,
            'description' => 'Calculated from exact immutable result and snapshot inputs. Full and strictly relevant samples remain separate.',
            'full_sample_count' => $profile->full_sample_count,
            'strict_sample_count' => $profile->strict_sample_count,
            'sample_evidence' => $profile->sample_evidence,
            'format_evidence' => $profile->format_evidence,
            'outlier_evidence' => $profile->outlier_evidence,
            'stability' => $profile->stability_evidence,
            'thresholds' => $profile->input_summary['thresholds'] ?? [],
            'warnings' => $profile->warnings ?? [],
            'calculated_at' => $profile->calculated_at->toIso8601String(),
        ];
    }
}
