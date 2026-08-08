<?php

namespace Tests\Unit\Domain\Discovery;

use App\Domain\Discovery\Data\BreakoutSignal;
use App\Domain\Discovery\Data\DiscoveryCandidateDraft;
use App\Domain\Discovery\Data\DiscoveryObservation;
use App\Domain\Discovery\Data\TopicPhraseSignal;
use App\Domain\Discovery\Services\BreakoutDetector;
use App\Domain\Discovery\Services\DeterministicClusteringProvider;
use App\Domain\Discovery\Services\DeterministicDiscoveryEngine;
use App\Domain\Discovery\Services\DeterministicTopicExpansionProvider;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DeterministicDiscoveryEngineTest extends TestCase
{
    public function test_identical_multilingual_observations_produce_identical_bounded_candidates(): void
    {
        $observations = [
            $this->observation('baseline-one', 'Idei generale pentru casă', 100, 0.2),
            $this->observation('baseline-two', 'Общие идеи для дома', 120, 0.3),
            $this->observation('breakout-one', 'Organizare apartament mic modern', 2400, 2.5),
            $this->observation('breakout-two', 'Organizare apartament mic eficient', 2200, 2.2),
        ];
        $engine = app(DeterministicDiscoveryEngine::class);

        $first = $engine->generate($observations);
        $second = $engine->generate($observations);

        $this->assertEquals($first, $second);
        $this->assertNotEmpty($first);
        $this->assertLessThanOrEqual(20, count($first));
        $this->assertContains(
            'organizare apartament',
            array_map(fn (DiscoveryCandidateDraft $candidate): string => $candidate->phrase, $first),
        );
        $this->assertSame(DeterministicDiscoveryEngine::FORMULA_VERSION, $first[0]->formulaVersion);
        $this->assertSame('returned_video_breakout', $first[0]->evidence['observed_signal']);
        $this->assertGreaterThanOrEqual(0, $first[0]->overallScore);
        $this->assertLessThanOrEqual(100, $first[0]->overallScore);
        $this->assertGreaterThanOrEqual(0, $first[0]->confidenceScore);
        $this->assertLessThanOrEqual(100, $first[0]->confidenceScore);
    }

    public function test_breakout_detection_has_deterministic_boundaries_ordering_and_missing_metric_behavior(): void
    {
        $detector = app(BreakoutDetector::class);
        $observations = [
            $this->observation('baseline-two', 'Baseline topic two', 100, 0.2),
            $this->observation('threshold-z', 'Threshold topic zeta', 150, 0.2),
            $this->observation('threshold-a', 'Threshold topic alpha', 150, 0.2),
            new DiscoveryObservation(
                seedId: 1,
                seedQuery: 'compact living',
                providerVideoId: 'missing-velocity',
                providerChannelId: 'channel-missing',
                title: 'Missing velocity but strong reach',
                publishedAt: CarbonImmutable::parse('2026-08-01 12:00:00 UTC'),
                viewCount: null,
                viewsPerDay: null,
                reachRatio: 2.0,
            ),
            $this->observation('baseline-one', 'Baseline topic one', 100, 0.2),
            $this->observation('baseline-three', 'Baseline topic three', 100, 0.2),
        ];

        $first = $detector->detect($observations);
        $second = $detector->detect(array_reverse($observations));

        $this->assertEquals($first, $second);
        $this->assertSame(
            ['threshold-a', 'threshold-z', 'missing-velocity'],
            array_map(
                fn (BreakoutSignal $signal): string => $signal->observation->providerVideoId,
                $first,
            ),
        );
        $this->assertSame(1.5, $first[0]->velocityMultiplier);
    }

    public function test_topic_extraction_normalizes_stop_words_and_deduplicates_evidence(): void
    {
        $provider = app(DeterministicTopicExpansionProvider::class);
        $observation = $this->observation(
            'breakout-one',
            'Best SMALL-apartment storage video!',
            2000,
            2.5,
        );
        $signals = [
            new BreakoutSignal($observation, 4.0, 90.0),
            new BreakoutSignal($observation, 4.0, 90.0),
        ];

        $phrases = $provider->expand($signals);
        $smallApartment = collect($phrases)->firstWhere('phraseKey', 'small apartment');

        $this->assertNotNull($smallApartment);
        $this->assertSame(['small', 'apartment'], $smallApartment->tokens);
        $this->assertSame(['breakout-one'], $smallApartment->videoIds);
        $this->assertSame(['compact living'], $smallApartment->seedQueries);
        $this->assertSame(180.0, $smallApartment->strength);
        $this->assertNotContains(
            'best small',
            array_map(fn (TopicPhraseSignal $phrase): string => $phrase->phraseKey, $phrases),
        );
    }

    public function test_clustering_is_input_order_independent_and_deduplicates_member_evidence(): void
    {
        $provider = app(DeterministicClusteringProvider::class);
        $phrases = [
            new TopicPhraseSignal(
                phrase: 'apartment small storage',
                phraseKey: 'apartment small storage',
                tokens: ['apartment', 'small', 'storage'],
                videoIds: ['video-two', 'video-one'],
                seedQueries: ['seed-two'],
                strength: 50,
            ),
            new TopicPhraseSignal(
                phrase: 'small apartment workshop',
                phraseKey: 'small apartment workshop',
                tokens: ['small', 'apartment', 'workshop'],
                videoIds: ['video-one'],
                seedQueries: ['seed-one', 'seed-two'],
                strength: 70,
            ),
        ];

        $first = $provider->cluster($phrases);
        $second = $provider->cluster(array_reverse($phrases));

        $this->assertEquals($first, $second);
        $this->assertCount(1, $first);
        $this->assertSame('small apartment workshop', $first[0]->representativePhrase);
        $this->assertSame(['video-one', 'video-two'], $first[0]->videoIds);
        $this->assertSame(['seed-one', 'seed-two'], $first[0]->seedQueries);
    }

    public function test_candidate_limit_is_honored_without_changing_deterministic_ranking(): void
    {
        $observations = [
            $this->observation('baseline-one', 'general home topics', 100, 0.2),
            $this->observation('baseline-two', 'general maker topics', 100, 0.2),
            $this->observation('breakout-one', 'small apartment storage ideas', 2500, 2.5),
            $this->observation('breakout-two', 'garden workshop tool systems', 2200, 2.2),
        ];
        $engine = app(DeterministicDiscoveryEngine::class);
        $all = $engine->generate($observations, 20);
        $limited = $engine->generate($observations, 1);

        $this->assertGreaterThan(1, count($all));
        $this->assertCount(1, $limited);
        $this->assertEquals($all[0], $limited[0]);
        $this->assertSame(
            count($all),
            count(array_unique(array_map(
                fn (DiscoveryCandidateDraft $candidate): string => mb_strtolower($candidate->phrase),
                $all,
            ))),
        );
    }

    private function observation(
        string $videoId,
        string $title,
        float $viewsPerDay,
        float $reachRatio,
    ): DiscoveryObservation {
        return new DiscoveryObservation(
            seedId: 1,
            seedQuery: 'compact living',
            providerVideoId: $videoId,
            providerChannelId: 'channel-'.$videoId,
            title: $title,
            publishedAt: CarbonImmutable::parse('2026-08-01 12:00:00 UTC'),
            viewCount: (int) ($viewsPerDay * 10),
            viewsPerDay: $viewsPerDay,
            reachRatio: $reachRatio,
        );
    }
}
