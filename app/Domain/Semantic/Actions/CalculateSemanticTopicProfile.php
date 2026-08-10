<?php

namespace App\Domain\Semantic\Actions;

use App\Domain\Semantic\Contracts\SemanticClassificationProvider;
use App\Domain\Semantic\Data\SemanticLabel;
use App\Domain\Semantic\Data\SemanticVideoInput;
use App\Models\AnalyzerRun;
use App\Models\SemanticTopicProfile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CalculateSemanticTopicProfile
{
    public function __construct(private SemanticClassificationProvider $provider) {}

    public function handle(AnalyzerRun $run): SemanticTopicProfile
    {
        $existing = SemanticTopicProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $memberships = $run->videoMemberships()->with('video')->orderByRaw("case when role = 'anchor' then 0 else 1 end")->orderBy('source_position')->get();
        $videos = array_values($memberships->map(fn ($membership): SemanticVideoInput => new SemanticVideoInput(
            providerVideoId: $membership->video->provider_video_id,
            title: $membership->video->title,
        ))->values()->all());

        try {
            $result = $this->provider->classify($videos);
        } catch (Throwable) {
            return SemanticTopicProfile::query()->create([
                'user_id' => $run->user_id,
                'analyzer_run_id' => $run->id,
                'status' => 'failed',
                'provenance' => 'inferred',
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
                'language' => 'und',
                'evidence_summary' => ['membership_ids' => $memberships->pluck('id')->all(), 'video_count' => count($videos)],
                'warnings' => ['Detected topic classification could not be calculated from the stored metadata.'],
                'calculated_at' => now(),
            ]);
        }

        return DB::transaction(function () use ($run, $memberships, $videos, $result): SemanticTopicProfile {
            $profile = SemanticTopicProfile::query()->create([
                'user_id' => $run->user_id,
                'analyzer_run_id' => $run->id,
                'status' => $result->status,
                'provenance' => 'inferred',
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
                'language' => $result->language,
                'niche_label' => $result->niche?->label,
                'niche_key' => $result->niche?->key,
                'niche_confidence' => $result->niche?->confidence,
                'subniche_label' => $result->subniche?->label,
                'subniche_key' => $result->subniche?->key,
                'subniche_confidence' => $result->subniche?->confidence,
                'concentration_score' => $result->concentrationScore,
                'confidence_score' => $result->confidenceScore,
                'evidence_summary' => [
                    'membership_ids' => $memberships->pluck('id')->all(),
                    'provider_video_ids' => array_map(fn (SemanticVideoInput $video): string => $video->providerVideoId, $videos),
                    'video_count' => count($videos),
                ],
                'warnings' => $result->warnings === [] ? null : $result->warnings,
                'calculated_at' => now(),
            ]);

            foreach ($result->topics as $position => $label) {
                $this->createLabel($profile, $label, $position + 1);
            }
            foreach ($result->contentPillars as $position => $label) {
                $this->createLabel($profile, $label, $position + 1);
            }

            return $profile->load('classifications');
        });
    }

    private function createLabel(SemanticTopicProfile $profile, SemanticLabel $label, int $position): void
    {
        $profile->classifications()->create([
            'kind' => $label->kind,
            'label' => $label->label,
            'label_key' => $label->key,
            'confidence' => $label->confidence,
            'position' => $position,
            'evidence_video_ids' => $label->evidenceVideoIds,
        ]);
    }
}
