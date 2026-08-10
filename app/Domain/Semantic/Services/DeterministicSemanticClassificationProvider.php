<?php

namespace App\Domain\Semantic\Services;

use App\Domain\Semantic\Contracts\SemanticClassificationProvider;
use App\Domain\Semantic\Data\SemanticLabel;
use App\Domain\Semantic\Data\SemanticProfileResult;
use App\Domain\Semantic\Data\SemanticVideoInput;
use Illuminate\Support\Str;

final class DeterministicSemanticClassificationProvider implements SemanticClassificationProvider
{
    private const STOP_WORDS = [
        'en' => ['about', 'after', 'and', 'are', 'best', 'for', 'from', 'how', 'into', 'more', 'that', 'the', 'this', 'video', 'with', 'you', 'your'],
        'ro' => ['care', 'cele', 'cel', 'cum', 'din', 'este', 'la', 'mai', 'pentru', 'sau', 'și', 'sunt', 'un', 'una', 'video'],
        'ru' => ['без', 'более', 'видео', 'для', 'как', 'что', 'это', 'или', 'из', 'на', 'по', 'при', 'свой'],
    ];

    public function name(): string
    {
        return 'deterministic_title_terms';
    }

    public function version(): string
    {
        return 'semantic-title-terms-v1';
    }

    public function classify(array $videos): SemanticProfileResult
    {
        $videos = array_values(array_filter(
            $videos,
            fn (SemanticVideoInput $video): bool => Str::squish($video->title) !== '',
        ));
        $uniqueVideos = [];
        foreach ($videos as $video) {
            $uniqueVideos[$video->providerVideoId] ??= $video;
        }
        $videos = array_values($uniqueVideos);

        if (count($videos) < 2) {
            return new SemanticProfileResult(
                status: 'insufficient',
                language: $videos === [] ? 'und' : $this->detectLanguage($videos[0]->title),
                niche: null,
                subniche: null,
                topics: [],
                contentPillars: [],
                concentrationScore: null,
                confidenceScore: null,
                warnings: ['At least two stored video titles are required for detected topic classification.'],
            );
        }

        [$language, $languageAgreement] = $this->dominantLanguage($videos);
        $documents = [];
        foreach ($videos as $video) {
            $tokens = $this->tokens($video->title, $language);
            if ($tokens !== []) {
                $documents[] = ['video_id' => $video->providerVideoId, 'tokens' => $tokens];
            }
        }

        if (count($documents) < 2) {
            return new SemanticProfileResult(
                status: 'insufficient',
                language: $language,
                niche: null,
                subniche: null,
                topics: [],
                contentPillars: [],
                concentrationScore: null,
                confidenceScore: null,
                warnings: ['Stored titles did not contain enough meaningful terms for classification.'],
            );
        }

        $phrases = $this->rankTerms($documents, 2);
        if ($phrases === []) {
            $phrases = $this->rankTerms($documents, 1);
        }
        $terms = $this->rankTerms($documents, 1);
        $topics = $this->labels('topic', array_slice($phrases, 0, 8), count($documents), $languageAgreement);
        $pillars = $this->labels('content_pillar', array_slice($terms, 0, 5), count($documents), $languageAgreement);

        if ($topics === []) {
            return new SemanticProfileResult(
                status: 'insufficient',
                language: $language,
                niche: null,
                subniche: null,
                topics: [],
                contentPillars: $pillars,
                concentrationScore: null,
                confidenceScore: null,
                warnings: ['No repeated or meaningful topic phrase could be inferred from the stored titles.'],
            );
        }

        $niche = $this->asKind($topics[0], 'niche');
        $subniche = isset($topics[1]) ? $this->asKind($topics[1], 'subniche') : null;
        $topicDocumentCounts = array_column(array_slice($phrases, 0, count($topics)), 'document_count');
        $totalTopicEvidence = array_sum($topicDocumentCounts);
        $concentration = $totalTopicEvidence > 0
            ? round(((int) $topicDocumentCounts[0] / $totalTopicEvidence) * 100, 4)
            : null;
        $coverage = count($documents) / count($videos);
        $recurrence = (int) $phrases[0]['document_count'] / count($documents);
        $confidence = round(min(95, 20 + (min(count($documents), 10) * 4) + ($coverage * 15) + ($recurrence * 15) + ($languageAgreement * 10)), 4);
        $warnings = [];
        $status = count($documents) < 3 || $languageAgreement < 0.6 ? 'partial' : 'complete';
        if (count($documents) < 3) {
            $warnings[] = 'Only two usable titles were available; classifications have limited coverage.';
        }
        if ($languageAgreement < 0.6) {
            $warnings[] = 'The title cohort contains mixed detected languages; classifications use the dominant language.';
        }

        return new SemanticProfileResult(
            status: $status,
            language: $language,
            niche: $niche,
            subniche: $subniche,
            topics: $topics,
            contentPillars: $pillars,
            concentrationScore: $concentration,
            confidenceScore: $confidence,
            warnings: $warnings,
        );
    }

    /** @param list<SemanticVideoInput> $videos
     * @return array{string, float}
     */
    private function dominantLanguage(array $videos): array
    {
        $counts = [];
        foreach ($videos as $video) {
            $language = $this->detectLanguage($video->title);
            $counts[$language] = ($counts[$language] ?? 0) + 1;
        }
        arsort($counts, SORT_NUMERIC);
        $language = (string) array_key_first($counts);

        return [$language, ((int) $counts[$language]) / count($videos)];
    }

    private function detectLanguage(string $title): string
    {
        $normalized = Str::lower($title);
        if (preg_match('/\p{Cyrillic}/u', $normalized) === 1) {
            return 'ru';
        }
        if (preg_match('/[ăâîșşțţ]/u', $normalized) === 1 || preg_match('/\b(apartament|care|cele|cum|depozitare|din|este|idei|organizare|pentru|și|sunt)\b/u', $normalized) === 1) {
            return 'ro';
        }

        return preg_match('/\p{Latin}/u', $normalized) === 1 ? 'en' : 'und';
    }

    /** @return list<string> */
    private function tokens(string $title, string $language): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', Str::lower($title)) ?? '';
        $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = self::STOP_WORDS[$language] ?? [];

        return array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3
            && ! is_numeric($token)
            && ! in_array($token, $stopWords, true)));
    }

    /**
     * @param  list<array{video_id: string, tokens: list<string>}>  $documents
     * @return list<array{key: string, label: string, document_count: int, occurrence_count: int, evidence_video_ids: list<string>}>
     */
    private function rankTerms(array $documents, int $size): array
    {
        $terms = [];
        foreach ($documents as $document) {
            $seen = [];
            for ($index = 0; $index <= count($document['tokens']) - $size; $index++) {
                $tokens = array_slice($document['tokens'], $index, $size);
                $key = implode(' ', $tokens);
                $terms[$key] ??= ['key' => $key, 'label' => $this->label($key), 'document_count' => 0, 'occurrence_count' => 0, 'evidence' => []];
                $terms[$key]['occurrence_count']++;
                $terms[$key]['evidence'][$document['video_id']] = true;
                if (! isset($seen[$key])) {
                    $terms[$key]['document_count']++;
                    $seen[$key] = true;
                }
            }
        }

        $ranked = array_values(array_map(fn (array $term): array => [
            'key' => $term['key'],
            'label' => $term['label'],
            'document_count' => $term['document_count'],
            'occurrence_count' => $term['occurrence_count'],
            'evidence_video_ids' => array_keys($term['evidence']),
        ], $terms));
        usort($ranked, fn (array $left, array $right): int => [
            -$left['document_count'],
            -$left['occurrence_count'],
            $left['key'],
        ] <=> [
            -$right['document_count'],
            -$right['occurrence_count'],
            $right['key'],
        ]);

        return array_values(array_filter($ranked, fn (array $term): bool => $term['document_count'] >= 2 || count($documents) <= 2));
    }

    /**
     * @param  list<array{key: string, label: string, document_count: int, occurrence_count: int, evidence_video_ids: list<string>}>  $terms
     * @return list<SemanticLabel>
     */
    private function labels(string $kind, array $terms, int $documentCount, float $languageAgreement): array
    {
        return array_map(fn (array $term): SemanticLabel => new SemanticLabel(
            kind: $kind,
            label: $term['label'],
            key: $term['key'],
            confidence: round(min(95, 30 + (($term['document_count'] / $documentCount) * 50) + ($languageAgreement * 15)), 4),
            evidenceVideoIds: $term['evidence_video_ids'],
        ), $terms);
    }

    private function asKind(SemanticLabel $label, string $kind): SemanticLabel
    {
        return new SemanticLabel($kind, $label->label, $label->key, $label->confidence, $label->evidenceVideoIds);
    }

    private function label(string $key): string
    {
        return mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');
    }
}
