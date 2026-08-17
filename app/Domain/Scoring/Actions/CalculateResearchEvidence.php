<?php

namespace App\Domain\Scoring\Actions;

use App\Domain\Discovery\Services\MultilingualPhraseNormalizer;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Scoring\Data\ResearchEvidenceVideoInput;
use App\Domain\Scoring\Services\ResearchEvidenceV1;
use App\Models\ResearchEvidenceProfile;
use App\Models\ResearchRun;
use DomainException;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CalculateResearchEvidence
{
    public function __construct(private ResearchEvidenceV1 $engine) {}

    public function handle(ResearchRun $run): ResearchEvidenceProfile
    {
        $existing = $run->evidenceProfiles()->where('evidence_version', ResearchEvidenceV1::VERSION)->first();
        if ($existing !== null) {
            return $existing;
        }
        if ($run->status !== ResearchRunStatus::Scoring) {
            throw new DomainException('Research evidence may only be calculated during the scoring stage.');
        }

        $signature = $this->compatibilitySignature($run);
        $calculation = $this->engine->calculate(
            query: $run->query_text,
            expectedLanguage: (string) ($run->parameters['language'] ?? $run->relevance_language),
            requestedFormat: (string) ($run->parameters['content_format'] ?? 'any'),
            videos: $this->inputs($run),
        );
        $previous = $this->previousCompatibleProfile($run, $signature);
        $previousRows = $previous === null ? [] : $this->storedRows($previous);
        $stability = $this->engine->stability($calculation['rows'], $previousRows);
        if ($previous !== null) {
            $stability['previous_research_run_id'] = $previous->research_run_id;
            $stability['previous_profile_id'] = $previous->id;
        }

        return DB::transaction(function () use ($run, $signature, $calculation, $stability): ResearchEvidenceProfile {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);
            $existing = $lockedRun->evidenceProfiles()->where('evidence_version', ResearchEvidenceV1::VERSION)->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($lockedRun->status !== ResearchRunStatus::Scoring) {
                throw new DomainException('The research run left scoring before evidence could be persisted.');
            }

            $profile = $lockedRun->evidenceProfiles()->create([
                'evidence_version' => ResearchEvidenceV1::VERSION,
                'normalization_version' => MultilingualPhraseNormalizer::VERSION,
                'full_sample_count' => count($calculation['rows']),
                'strict_sample_count' => (int) ($calculation['sample_evidence']['strict']['count'] ?? 0),
                'input_summary' => [
                    'compatibility_signature' => $signature,
                    'query_text' => $lockedRun->query_text,
                    'market_key' => $lockedRun->market_key,
                    'relevance_language' => $lockedRun->relevance_language,
                    'requested_format' => $lockedRun->parameters['content_format'] ?? 'any',
                    'thresholds' => [
                        'strict_score' => 75,
                        'strict_title_coverage' => 0.75,
                        'robust_minimum_sample' => ResearchEvidenceV1::MIN_ROBUST_SAMPLE,
                        'trimmed_mean_minimum_sample' => ResearchEvidenceV1::MIN_TRIMMED_SAMPLE,
                        'stability_minimum_overlap' => ResearchEvidenceV1::MIN_STABILITY_OVERLAP,
                    ],
                ],
                'sample_evidence' => $calculation['sample_evidence'],
                'format_evidence' => $calculation['format_evidence'],
                'outlier_evidence' => $calculation['outlier_evidence'],
                'stability_evidence' => $stability,
                'warnings' => $calculation['warnings'] === [] ? null : $calculation['warnings'],
                'calculated_at' => now(),
            ]);

            foreach ($calculation['rows'] as $row) {
                $profile->results()->create([
                    'research_run_id' => $lockedRun->id,
                    'video_id' => $row['video_id'],
                    'channel_id' => $row['channel_id'],
                    'video_snapshot_id' => $row['video_snapshot_id'],
                    'channel_snapshot_id' => $row['channel_snapshot_id'],
                    'relevance_class' => $row['relevance_class'],
                    'relevance_score' => $row['relevance_score'],
                    'format_class' => $row['format_class'],
                    'signals' => $row['signals'],
                    'metric_inputs' => [
                        'provider_video_id' => $row['provider_video_id'],
                        'result_rank' => $row['result_rank'],
                        'views_per_day' => $row['views_per_day'],
                        'view_count' => $row['view_count'],
                    ],
                ]);
            }

            return $profile;
        });
    }

    /** @return list<ResearchEvidenceVideoInput> */
    private function inputs(ResearchRun $run): array
    {
        /** @var list<stdClass> $records */
        $records = DB::table('research_run_videos as membership')
            ->join('videos as video', 'video.id', '=', 'membership.video_id')
            ->leftJoin('video_snapshots as snapshot', 'snapshot.id', '=', 'membership.video_snapshot_id')
            ->where('membership.research_run_id', $run->id)
            ->orderBy('membership.result_rank')
            ->select([
                'membership.video_id', 'membership.video_snapshot_id', 'membership.channel_snapshot_id',
                'membership.result_rank', 'membership.matched_query_metadata', 'video.channel_id',
                'video.provider_video_id', 'video.title', 'video.category_id', 'video.is_short',
                'snapshot.views_per_day', 'snapshot.view_count',
            ])
            ->selectSub(
                DB::table('video_categories as category')
                    ->select('category.name')
                    ->whereColumn('category.category_id', 'video.category_id')
                    ->where('category.provider', 'youtube')
                    ->where('category.display_language', 'en')
                    ->orderBy('category.id')
                    ->limit(1),
                'category_name',
            )
            ->get()->all();

        return array_map(function (stdClass $record): ResearchEvidenceVideoInput {
            $metadata = $this->metadata($record->matched_query_metadata);

            return new ResearchEvidenceVideoInput(
                videoId: (int) $record->video_id,
                channelId: (int) $record->channel_id,
                videoSnapshotId: $record->video_snapshot_id !== null ? (int) $record->video_snapshot_id : null,
                channelSnapshotId: $record->channel_snapshot_id !== null ? (int) $record->channel_snapshot_id : null,
                providerVideoId: (string) $record->provider_video_id,
                title: (string) $record->title,
                categoryName: $record->category_name !== null ? (string) $record->category_name : null,
                semanticLabels: $this->strings($metadata['semantic_labels'] ?? []),
                topicLabels: $this->strings($metadata['topic_labels'] ?? []),
                isShort: $record->is_short !== null ? (bool) $record->is_short : null,
                viewsPerDay: $record->views_per_day !== null ? (float) $record->views_per_day : null,
                viewCount: $record->view_count !== null ? (int) $record->view_count : null,
                resultRank: (int) $record->result_rank,
            );
        }, $records);
    }

    private function previousCompatibleProfile(ResearchRun $run, string $signature): ?ResearchEvidenceProfile
    {
        return ResearchEvidenceProfile::query()
            ->join('research_runs', 'research_runs.id', '=', 'research_evidence_profiles.research_run_id')
            ->where('research_runs.user_id', $run->user_id)
            ->where('research_runs.status', ResearchRunStatus::Completed->value)
            ->where('research_runs.id', '<', $run->id)
            ->where('research_evidence_profiles.evidence_version', ResearchEvidenceV1::VERSION)
            ->where('research_evidence_profiles.input_summary->compatibility_signature', $signature)
            ->orderByDesc('research_runs.completed_at')
            ->orderByDesc('research_runs.id')
            ->select('research_evidence_profiles.*')
            ->first();
    }

    /** @return list<array<string, mixed>> */
    private function storedRows(ResearchEvidenceProfile $profile): array
    {
        $rows = [];
        foreach ($profile->results()->orderBy('id')->get() as $result) {
            $inputs = $result->metric_inputs;

            $rows[] = [
                'provider_video_id' => (string) ($inputs['provider_video_id'] ?? ''),
                'channel_id' => $result->channel_id,
                'result_rank' => (int) ($inputs['result_rank'] ?? 0),
                'views_per_day' => is_numeric($inputs['views_per_day'] ?? null) ? (float) $inputs['views_per_day'] : null,
            ];
        }

        return $rows;
    }

    private function compatibilitySignature(ResearchRun $run): string
    {
        $parameters = $run->parameters;
        ksort($parameters);

        return hash('sha256', json_encode([
            'query' => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $run->query_text) ?? $run->query_text)),
            'kind' => $run->kind->value,
            'market_key' => $run->market_key,
            'region_code' => $run->region_code,
            'relevance_language' => $run->relevance_language,
            'requested_result_count' => $run->requested_result_count,
            'parameters' => $parameters,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
