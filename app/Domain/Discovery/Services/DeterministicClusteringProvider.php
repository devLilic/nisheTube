<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\ClusteringProvider;
use App\Domain\Discovery\Data\DiscoveryCluster;
use App\Domain\Discovery\Data\TopicPhraseSignal;
use Illuminate\Support\Str;

class DeterministicClusteringProvider implements ClusteringProvider
{
    public function cluster(array $phrases): array
    {
        /** @var array<string, list<TopicPhraseSignal>> $groups */
        $groups = [];

        foreach ($phrases as $phrase) {
            $tokens = $phrase->tokens;
            sort($tokens, SORT_STRING);
            $signature = implode('|', array_slice(array_values(array_unique($tokens)), 0, 2));
            $clusterKey = substr(hash('sha256', $signature), 0, 32);
            $groups[$clusterKey][] = $phrase;
        }

        $clusters = [];

        foreach ($groups as $clusterKey => $members) {
            usort($members, fn (TopicPhraseSignal $left, TopicPhraseSignal $right): int => [
                -$left->strength,
                $left->phraseKey,
            ] <=> [
                -$right->strength,
                $right->phraseKey,
            ]);
            $videoIds = [];
            $seedQueries = [];

            foreach ($members as $member) {
                foreach ($member->videoIds as $videoId) {
                    $videoIds[$videoId] = true;
                }

                foreach ($member->seedQueries as $seedQuery) {
                    $seedQueries[$seedQuery] = true;
                }
            }

            $clusters[] = new DiscoveryCluster(
                clusterKey: $clusterKey,
                representativePhrase: Str::squish($members[0]->phrase),
                memberPhrases: array_map(fn (TopicPhraseSignal $member): string => $member->phrase, $members),
                videoIds: array_keys($videoIds),
                seedQueries: array_keys($seedQueries),
                strength: round(array_sum(array_map(fn (TopicPhraseSignal $member): float => $member->strength, $members)), 4),
            );
        }

        usort($clusters, fn (DiscoveryCluster $left, DiscoveryCluster $right): int => [
            -$left->strength,
            $left->representativePhrase,
        ] <=> [
            -$right->strength,
            $right->representativePhrase,
        ]);

        return $clusters;
    }
}
