<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\ClusteringProvider;
use App\Domain\Discovery\Contracts\TopicExpansionProvider;
use App\Domain\Discovery\Data\DiscoveryCandidateDraft;
use App\Domain\Discovery\Data\DiscoveryObservation;

class DeterministicDiscoveryEngine
{
    public const FORMULA_VERSION = 'discovery-breakout-v1';

    public function __construct(
        private readonly BreakoutDetector $breakoutDetector,
        private readonly TopicExpansionProvider $topicExpansion,
        private readonly ClusteringProvider $clustering,
    ) {}

    /**
     * @param  list<DiscoveryObservation>  $observations
     * @return list<DiscoveryCandidateDraft>
     */
    public function generate(array $observations, int $candidateLimit = 20): array
    {
        $candidateLimit = max(1, min($candidateLimit, 20));
        $breakouts = $this->breakoutDetector->detect($observations);
        $clusters = $this->clustering->cluster($this->topicExpansion->expand($breakouts));
        $drafts = [];

        foreach (array_slice($clusters, 0, $candidateLimit) as $cluster) {
            $videoCount = count($cluster->videoIds);
            $seedCount = count($cluster->seedQueries);
            $drafts[] = new DiscoveryCandidateDraft(
                phrase: $cluster->representativePhrase,
                clusterKey: $cluster->clusterKey,
                summary: "Observed across {$videoCount} breakout video".($videoCount === 1 ? '' : 's')." from {$seedCount} seed".($seedCount === 1 ? '' : 's').'.',
                evidence: [
                    'observed_signal' => 'returned_video_breakout',
                    'video_ids' => $cluster->videoIds,
                    'seed_queries' => $cluster->seedQueries,
                    'member_phrases' => $cluster->memberPhrases,
                    'source_video_count' => $videoCount,
                    'seed_count' => $seedCount,
                ],
                overallScore: round(min($cluster->strength / max($videoCount, 1), 100), 4),
                confidenceScore: round(min(25 + ($videoCount * 15) + ($seedCount * 10), 100), 4),
                formulaVersion: self::FORMULA_VERSION,
            );
        }

        return $drafts;
    }
}
