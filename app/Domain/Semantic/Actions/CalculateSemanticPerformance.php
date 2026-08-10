<?php

namespace App\Domain\Semantic\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Semantic\Contracts\EditorialTitlePatternProvider;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\SemanticPerformanceProfile;
use App\Models\SemanticTopicProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CalculateSemanticPerformance
{
    public function __construct(private EditorialTitlePatternProvider $titlePatterns) {}

    public function handle(AnalyzerRun $run): SemanticPerformanceProfile
    {
        $existing = SemanticPerformanceProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $minimumSample = max(2, min(20, (int) config('analyzer.semantic_performance.minimum_sample_size', 2)));
        $topicProfile = SemanticTopicProfile::query()
            ->where('user_id', $run->user_id)
            ->where('analyzer_run_id', $run->id)
            ->with('classifications')
            ->first();
        $memberships = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::ChannelRecentUpload)
            ->with(['video', 'videoSnapshot'])
            ->orderBy('source_position')
            ->get();

        try {
            $groups = $this->groups($memberships, $topicProfile);
        } catch (Throwable) {
            return SemanticPerformanceProfile::query()->create([
                'user_id' => $run->user_id,
                'analyzer_run_id' => $run->id,
                'semantic_topic_profile_id' => $topicProfile?->id,
                'status' => 'failed',
                'provenance' => 'inferred',
                'calculation_version' => (string) config('analyzer.semantic_performance.version', 'semantic-performance-v1'),
                'topic_version' => $topicProfile?->algorithm_version,
                'title_pattern_version' => $this->titlePatterns->version(),
                'minimum_sample_size' => $minimumSample,
                'cohort_video_count' => $memberships->count(),
                'warnings' => ['Topic and title-pattern performance could not be calculated from the stored cohort.'],
                'calculated_at' => now(),
            ]);
        }

        return DB::transaction(function () use ($run, $topicProfile, $memberships, $groups, $minimumSample): SemanticPerformanceProfile {
            $warnings = $this->warnings($memberships, $groups, $topicProfile, $minimumSample);
            $hasClassifiedTopic = $this->hasQualifiedGroup($groups['topic'], $minimumSample);
            $hasClassifiedPattern = $this->hasQualifiedGroup($groups['title_pattern'], $minimumSample);
            $status = $memberships->count() < $minimumSample || (! $hasClassifiedTopic && ! $hasClassifiedPattern)
                ? 'insufficient'
                : ($warnings === [] ? 'complete' : 'partial');

            $profile = SemanticPerformanceProfile::query()->create([
                'user_id' => $run->user_id,
                'analyzer_run_id' => $run->id,
                'semantic_topic_profile_id' => $topicProfile?->id,
                'status' => $status,
                'provenance' => 'inferred',
                'calculation_version' => (string) config('analyzer.semantic_performance.version', 'semantic-performance-v1'),
                'topic_version' => $topicProfile?->algorithm_version,
                'title_pattern_version' => $this->titlePatterns->version(),
                'minimum_sample_size' => $minimumSample,
                'cohort_video_count' => $memberships->count(),
                'warnings' => $warnings === [] ? null : $warnings,
                'calculated_at' => now(),
            ]);

            foreach (['topic', 'title_pattern'] as $groupType) {
                $position = 1;
                foreach ($this->orderedGroups($groups[$groupType]) as $groupKey => $group) {
                    $profile->aggregates()->create([
                        ...$this->aggregate($groupType, $groupKey, $group, $minimumSample),
                        'position' => $position++,
                    ]);
                }
            }

            return $profile->load('aggregates');
        });
    }

    /**
     * @param  Collection<int, AnalyzerRunVideo>  $memberships
     * @return array{topic: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>, title_pattern: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>}
     */
    private function groups(Collection $memberships, ?SemanticTopicProfile $topicProfile): array
    {
        $groups = $this->emptyGroups();
        $topicKeysByVideo = $this->topicAssignments($topicProfile);

        foreach ($memberships as $membership) {
            $providerVideoId = $membership->video->provider_video_id;
            $topics = $topicKeysByVideo[$providerVideoId] ?? [];
            if ($topics === []) {
                $topics = [['key' => '__unclassified__', 'label' => 'Unclassified topic']];
            }
            foreach ($topics as $topic) {
                $this->addToGroup($groups['topic'], $topic['key'], $topic['label'], $membership);
            }

            $patterns = $this->titlePatterns->detect($membership->video->title);
            if ($patterns === []) {
                $this->addToGroup($groups['title_pattern'], '__unclassified__', 'Unclassified pattern', $membership);
            } else {
                foreach ($patterns as $pattern) {
                    $this->addToGroup($groups['title_pattern'], $pattern->key, $pattern->label, $membership);
                }
            }
        }

        return $groups;
    }

    /**
     * @return array{topic: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>, title_pattern: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>}
     */
    private function emptyGroups(): array
    {
        return ['topic' => [], 'title_pattern' => []];
    }

    /** @return array<string, list<array{key: string, label: string}>> */
    private function topicAssignments(?SemanticTopicProfile $topicProfile): array
    {
        $assignments = [];
        if ($topicProfile === null) {
            return $assignments;
        }

        foreach ($topicProfile->classifications->where('kind', 'topic') as $classification) {
            foreach ($classification->evidence_video_ids as $providerVideoId) {
                $assignments[$providerVideoId][] = [
                    'key' => $classification->label_key,
                    'label' => $classification->label,
                ];
            }
        }

        return $assignments;
    }

    /** @param array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}> $groups */
    private function addToGroup(array &$groups, string $key, string $label, AnalyzerRunVideo $membership): void
    {
        $groups[$key] ??= ['label' => $label, 'unclassified' => $key === '__unclassified__', 'memberships' => []];
        $groups[$key]['memberships'][$membership->id] = $membership;
    }

    /**
     * @param  array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>  $groups
     * @return array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>
     */
    private function orderedGroups(array $groups): array
    {
        uasort($groups, fn (array $left, array $right): int => [
            $left['unclassified'] ? 1 : 0,
            -count($left['memberships']),
            mb_strtolower($left['label']),
        ] <=> [
            $right['unclassified'] ? 1 : 0,
            -count($right['memberships']),
            mb_strtolower($right['label']),
        ]);

        return $groups;
    }

    /**
     * @param  array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}  $group
     * @return array<string, mixed>
     */
    private function aggregate(string $groupType, string $groupKey, array $group, int $minimumSample): array
    {
        $memberships = array_values($group['memberships']);
        $sampleCount = count($memberships);
        $views = array_values(array_map(
            fn (AnalyzerRunVideo $membership): int => $membership->videoSnapshot->view_count,
            array_filter($memberships, fn (AnalyzerRunVideo $membership): bool => $membership->videoSnapshot->view_count !== null),
        ));
        $viewsPerDay = array_values(array_map(
            fn (AnalyzerRunVideo $membership): float => (float) $membership->videoSnapshot->views_per_day,
            array_filter($memberships, fn (AnalyzerRunVideo $membership): bool => $membership->videoSnapshot->views_per_day !== null),
        ));
        $breakoutEvidence = array_values(array_filter(
            $memberships,
            fn (AnalyzerRunVideo $membership): bool => $membership->breakout_class !== null,
        ));
        $breakoutCount = count(array_filter(
            $breakoutEvidence,
            fn (AnalyzerRunVideo $membership): bool => $membership->breakout_class === 'breakout',
        ));

        return [
            'group_type' => $groupType,
            'label' => $group['label'],
            'label_key' => $groupKey,
            'is_unclassified' => $group['unclassified'],
            'meets_minimum_sample' => $sampleCount >= $minimumSample,
            'sample_count' => $sampleCount,
            'view_sample_count' => count($views),
            'median_views' => count($views) >= $minimumSample ? $this->median($views) : null,
            'average_views' => count($views) >= $minimumSample ? round(array_sum($views) / count($views), 4) : null,
            'views_per_day_sample_count' => count($viewsPerDay),
            'median_views_per_day' => count($viewsPerDay) >= $minimumSample ? $this->median($viewsPerDay) : null,
            'average_views_per_day' => count($viewsPerDay) >= $minimumSample ? round(array_sum($viewsPerDay) / count($viewsPerDay), 6) : null,
            'breakout_sample_count' => count($breakoutEvidence),
            'breakout_count' => $breakoutCount,
            'breakout_rate_percent' => count($breakoutEvidence) >= $minimumSample
                ? round(($breakoutCount / count($breakoutEvidence)) * 100, 4)
                : null,
            'evidence_video_ids' => array_map(
                fn (AnalyzerRunVideo $membership): string => $membership->video->provider_video_id,
                $memberships,
            ),
        ];
    }

    /** @param list<int|float> $values */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return round($count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 6);
    }

    /**
     * @param  Collection<int, AnalyzerRunVideo>  $memberships
     * @param  array{topic: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>, title_pattern: array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}>}  $groups
     * @return list<string>
     */
    private function warnings(Collection $memberships, array $groups, ?SemanticTopicProfile $topicProfile, int $minimumSample): array
    {
        $warnings = [];
        if ($memberships->count() < $minimumSample) {
            $warnings[] = "At least {$minimumSample} recent cohort videos are required for performance aggregates.";
        }
        if ($topicProfile === null || ! in_array($topicProfile->status, ['complete', 'partial'], true)) {
            $warnings[] = 'Detected topics were unavailable; the topic view is unclassified while title-pattern detection remains independent.';
        }
        if (($topicProfile?->status) === 'partial') {
            $warnings[] = 'Topic groups inherit the partial or mixed-language coverage of the detected Topic Profile.';
        }
        foreach (['topic' => 'topic', 'title_pattern' => 'title-pattern'] as $type => $label) {
            if (isset($groups[$type]['__unclassified__'])) {
                $warnings[] = "Some recent videos have no classified {$label}; they remain visible in an Unclassified group.";
            }
        }

        return $warnings;
    }

    /** @param array<string, array{label: string, unclassified: bool, memberships: array<int, AnalyzerRunVideo>}> $groups */
    private function hasQualifiedGroup(array $groups, int $minimumSample): bool
    {
        foreach ($groups as $group) {
            if (! $group['unclassified'] && count($group['memberships']) >= $minimumSample) {
                return true;
            }
        }

        return false;
    }
}
