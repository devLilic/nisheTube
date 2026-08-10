<?php

namespace App\Domain\Transcripts\Services;

use App\Domain\Transcripts\Contracts\TranscriptProvider;
use App\Domain\Transcripts\Data\TranscriptParseResult;
use App\Domain\Transcripts\Data\TranscriptSegmentData;
use DomainException;
use Illuminate\Support\Str;

final class UserProvidedTranscriptProvider implements TranscriptProvider
{
    public function name(): string
    {
        return 'user_provided';
    }

    public function version(): string
    {
        return 'user-provided-transcript-v1';
    }

    public function parse(string $sourceText): TranscriptParseResult
    {
        $normalized = $this->normalize($sourceText);
        if ($normalized === '') {
            throw new DomainException('Paste a transcript containing readable text.');
        }

        if (str_contains($normalized, '-->')) {
            return $this->parseCueFormat($normalized);
        }

        $timestamped = $this->parseBracketedTimestamps($normalized);
        if ($timestamped !== null) {
            return $timestamped;
        }

        return new TranscriptParseResult(
            status: 'available',
            format: 'plain_text',
            plainText: $normalized,
            segments: [new TranscriptSegmentData(null, null, $normalized)],
            warnings: ['Plain text has no timestamps, so source navigation and offset evidence are unavailable.'],
        );
    }

    private function parseBracketedTimestamps(string $sourceText): ?TranscriptParseResult
    {
        $segments = [];
        $unparsed = 0;
        foreach (preg_split('/\R/u', $sourceText) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\[(?<time>(?:\d{1,2}:)?\d{1,2}:\d{2})\]\s*(?<text>.+)$/u', $line, $matches) !== 1) {
                $unparsed++;

                continue;
            }
            $text = Str::squish($matches['text']);
            if ($text !== '') {
                $segments[] = new TranscriptSegmentData($this->timestampToMs($matches['time']), null, $text);
            }
        }

        if ($segments === []) {
            return null;
        }
        if (count($segments) > 3000) {
            throw new DomainException('The pasted transcript may not contain more than 3,000 timestamped segments.');
        }

        $segments = $this->withDerivedEndTimes($segments);
        $warnings = $unparsed > 0 ? ["{$unparsed} non-empty line(s) without a leading timestamp were omitted."] : [];

        return new TranscriptParseResult(
            status: $unparsed > 0 ? 'partial' : 'available',
            format: 'timestamped_text',
            plainText: implode("\n", array_map(fn (TranscriptSegmentData $segment): string => $segment->text, $segments)),
            segments: $segments,
            warnings: $warnings,
        );
    }

    private function parseCueFormat(string $sourceText): TranscriptParseResult
    {
        $format = str_starts_with(ltrim($sourceText), 'WEBVTT') ? 'vtt' : 'srt';
        $blocks = preg_split('/\R\s*\R/u', $sourceText) ?: [];
        $segments = [];
        $unparsed = 0;

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', trim($block)) ?: []), fn (string $line): bool => $line !== ''));
            if ($lines === [] || $lines[0] === 'WEBVTT' || str_starts_with($lines[0], 'NOTE')) {
                continue;
            }
            $timingIndex = null;
            foreach ($lines as $index => $line) {
                if (str_contains($line, '-->')) {
                    $timingIndex = $index;
                    break;
                }
            }
            if ($timingIndex === null || preg_match('/(?<start>(?:\d{1,2}:)?\d{1,2}:\d{2}[,.]\d{3})\s*-->\s*(?<end>(?:\d{1,2}:)?\d{1,2}:\d{2}[,.]\d{3})/u', $lines[$timingIndex], $matches) !== 1) {
                $unparsed++;

                continue;
            }
            $text = Str::squish(strip_tags(implode(' ', array_slice($lines, $timingIndex + 1))));
            if ($text === '') {
                $unparsed++;

                continue;
            }
            $segments[] = new TranscriptSegmentData(
                $this->timestampToMs(str_replace(',', '.', $matches['start'])),
                $this->timestampToMs(str_replace(',', '.', $matches['end'])),
                $text,
            );
        }

        if ($segments === []) {
            throw new DomainException('No readable timestamped transcript cues were found.');
        }
        if (count($segments) > 3000) {
            throw new DomainException('The pasted transcript may not contain more than 3,000 timestamped segments.');
        }

        return new TranscriptParseResult(
            status: $unparsed > 0 ? 'partial' : 'available',
            format: $format,
            plainText: implode("\n", array_map(fn (TranscriptSegmentData $segment): string => $segment->text, $segments)),
            segments: $segments,
            warnings: $unparsed > 0 ? ["{$unparsed} malformed or empty cue(s) were omitted."] : [],
        );
    }

    private function normalize(string $sourceText): string
    {
        $sourceText = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $sourceText);
        $sourceText = preg_replace('/\x{00C2}(?=\s)/u', '', $sourceText) ?? $sourceText;
        $sourceText = preg_replace('/[ \t]+/u', ' ', $sourceText) ?? $sourceText;
        $sourceText = preg_replace('/ *\n */u', "\n", $sourceText) ?? $sourceText;
        $sourceText = preg_replace('/\n{3,}/u', "\n\n", $sourceText) ?? $sourceText;

        return trim($sourceText);
    }

    private function timestampToMs(string $timestamp): int
    {
        $timestamp = str_replace(',', '.', $timestamp);
        $parts = explode(':', $timestamp);
        $seconds = (float) array_pop($parts);
        $minutes = (int) array_pop($parts);
        $hours = $parts === [] ? 0 : (int) array_pop($parts);

        return (int) round((($hours * 3600) + ($minutes * 60) + $seconds) * 1000);
    }

    /** @param list<TranscriptSegmentData> $segments
     * @return list<TranscriptSegmentData>
     */
    private function withDerivedEndTimes(array $segments): array
    {
        return array_map(fn (TranscriptSegmentData $segment, int $index): TranscriptSegmentData => new TranscriptSegmentData(
            $segment->startMs,
            $segments[$index + 1]->startMs ?? null,
            $segment->text,
        ), $segments, array_keys($segments));
    }
}
