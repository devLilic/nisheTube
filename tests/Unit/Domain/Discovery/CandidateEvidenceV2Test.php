<?php

namespace Tests\Unit\Domain\Discovery;

use App\Domain\Discovery\Data\DiscoveryCandidateDraft;
use App\Domain\Discovery\Data\DiscoveryObservation;
use App\Domain\Discovery\Enums\CandidateEvidenceState;
use App\Domain\Discovery\Services\DeterministicDiscoveryEngine;
use App\Domain\Discovery\Services\MultilingualPhraseNormalizer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CandidateEvidenceV2Test extends TestCase
{
    public function test_multilingual_synonyms_and_transliterations_share_a_normalized_evidence_cluster(): void
    {
        $normalizer = app(MultilingualPhraseNormalizer::class);

        $this->assertSame('apartment storage', $normalizer->normalize('Apartament depozitare')->key);
        $this->assertSame('apartment storage', $normalizer->normalize('Квартира хранение')->key);
        $this->assertSame('apartment storage', $normalizer->normalize('kvartira hranenie')->key);

        $candidate = $this->candidate($this->healthyMultilingualEvidence());

        $this->assertSame(CandidateEvidenceState::Candidate, $candidate->evidenceState);
        $languages = $candidate->evidence['detected_languages'];
        $this->assertIsArray($languages);
        sort($languages);
        $this->assertSame(['en', 'ro', 'ru'], $languages);
        $this->assertContains('synonyms_or_transliterations', $candidate->evidence['normalization_transformations']);
        $this->assertSame(3, $candidate->evidence['source_video_count']);
        $this->assertSame(3, $candidate->evidence['unique_channel_count']);
        $this->assertSame([], $candidate->evidence['insufficiency_reasons']);
    }

    public function test_one_video_never_scores_as_a_candidate_or_above_the_single_video_ceiling(): void
    {
        $candidate = $this->candidate([
            $this->observation('baseline-a', 'general home basics', 10, 0.2, 'baseline-a', 500000),
            $this->observation('baseline-b', 'general room basics', 12, 0.2, 'baseline-b', 500000),
            $this->observation('viral', 'apartment storage guide', 10000, 5, 'only-channel', 12000),
        ]);

        $this->assertSame(CandidateEvidenceState::WeakPhraseSignal, $candidate->evidenceState);
        $this->assertLessThanOrEqual(35, $candidate->overallScore);
        $this->assertLessThanOrEqual(35, $candidate->confidenceScore);
        $this->assertContains('At least three supporting videos are required.', $candidate->evidence['insufficiency_reasons']);
        $this->assertTrue($candidate->evidence['outlier_dependent']);
    }

    public function test_minimum_channel_boundary_and_viral_outlier_dependency_remain_explicit(): void
    {
        $sameChannel = array_map(
            fn (DiscoveryObservation $observation): DiscoveryObservation => new DiscoveryObservation(
                seedId: $observation->seedId,
                seedQuery: $observation->seedQuery,
                providerVideoId: $observation->providerVideoId,
                providerChannelId: 'one-channel',
                title: $observation->title,
                publishedAt: $observation->publishedAt,
                viewCount: $observation->viewCount,
                viewsPerDay: $observation->viewsPerDay,
                reachRatio: $observation->reachRatio,
                subscriberCount: $observation->subscriberCount,
            ),
            $this->healthyMultilingualEvidence(),
        );
        $channelBoundary = $this->candidate($sameChannel);

        $this->assertSame(CandidateEvidenceState::WeakPhraseSignal, $channelBoundary->evidenceState);
        $this->assertContains('Evidence must span at least two unique channels.', $channelBoundary->evidence['insufficiency_reasons']);

        $outlier = $this->candidate([
            $this->observation('base-a', 'general home basics', 20, 0.1, 'base-a', 500000),
            $this->observation('base-b', 'general room basics', 20, 0.1, 'base-b', 500000),
            $this->observation('one', 'apartment storage guide', 30, 2, 'channel-one', 20000),
            $this->observation('two', 'apartment storage ideas', 30, 2, 'channel-two', 25000),
            $this->observation('three', 'apartment storage systems', 1000, 4, 'channel-three', 30000),
        ]);

        $this->assertSame(CandidateEvidenceState::WeakPhraseSignal, $outlier->evidenceState);
        $this->assertTrue($outlier->evidence['outlier_dependent']);
        $this->assertGreaterThan(0.7, $outlier->evidence['top_video_performance_share']);
    }

    public function test_incomplete_generic_phrases_are_retained_as_weak_signals_with_original_evidence(): void
    {
        $candidate = $this->candidate([
            $this->observation('base-a', 'unrelated baseline sample', 10, 0.2, 'base-a', 500000),
            $this->observation('base-b', 'another baseline sample', 10, 0.2, 'base-b', 500000),
            $this->observation('one', 'general topic ideas', 100, 2, 'one', 20000, 'general topic'),
            $this->observation('two', 'general topic video', 105, 2, 'two', 22000, 'general topic'),
            $this->observation('three', 'general topic guide', 110, 2, 'three', 25000, 'general topic'),
        ]);

        $this->assertSame(CandidateEvidenceState::WeakPhraseSignal, $candidate->evidenceState);
        $this->assertNotEmpty($candidate->evidence['original_phrases']);
        $this->assertSame('general topic', $candidate->evidence['suggested_validation_query']);
    }

    /** @param list<DiscoveryObservation> $observations */
    private function candidate(array $observations): DiscoveryCandidateDraft
    {
        $candidates = app(DeterministicDiscoveryEngine::class)->generate(
            $observations,
            20,
            DeterministicDiscoveryEngine::FORMULA_VERSION,
            DeterministicDiscoveryEngine::THRESHOLDS,
            CarbonImmutable::parse('2026-08-15 12:00:00 UTC'),
        );

        return collect($candidates)->first(
            fn (DiscoveryCandidateDraft $candidate): bool => in_array($candidate->phrase, ['apartment storage', 'general topic'], true),
        ) ?? $this->fail('Expected normalized candidate was not generated.');
    }

    /** @return list<DiscoveryObservation> */
    private function healthyMultilingualEvidence(): array
    {
        return [
            $this->observation('base-a', 'general home basics', 20, 0.2, 'base-a', 500000),
            $this->observation('base-b', 'general room basics', 25, 0.2, 'base-b', 500000),
            $this->observation('one', 'Apartment storage guide', 200, 2.5, 'channel-one', 20000),
            $this->observation('two', 'Apartament depozitare idei', 220, 2.6, 'channel-two', 30000),
            $this->observation('three', 'Квартира хранение руководство', 210, 2.4, 'channel-three', 40000),
        ];
    }

    private function observation(
        string $videoId,
        string $title,
        float $viewsPerDay,
        float $reachRatio,
        string $channelId,
        ?int $subscribers,
        string $seed = 'apartment storage',
    ): DiscoveryObservation {
        return new DiscoveryObservation(
            seedId: 1,
            seedQuery: $seed,
            providerVideoId: $videoId,
            providerChannelId: $channelId,
            title: $title,
            publishedAt: CarbonImmutable::parse('2026-08-01 12:00:00 UTC'),
            viewCount: (int) ($viewsPerDay * 10),
            viewsPerDay: $viewsPerDay,
            reachRatio: $reachRatio,
            subscriberCount: $subscribers,
        );
    }
}
