<?php

namespace Tests\Unit\Domain\Transcripts;

use App\Domain\Transcripts\Services\UserProvidedTranscriptProvider;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserProvidedTranscriptProviderTest extends TestCase
{
    #[Test]
    public function it_prefers_timestamped_text_and_normalizes_copy_artifacts(): void
    {
        $result = (new UserProvidedTranscriptProvider)->parse(<<<'TEXT'
[0:00] A dumpling can trap soup inside dough, ifÂ  sealed perfectly.
[0:09] Can you wrap dough around a filling?
[1:02] Twelve dishes get laid out that night.
TEXT);

        self::assertSame('available', $result->status);
        self::assertSame('timestamped_text', $result->format);
        self::assertCount(3, $result->segments);
        self::assertSame(0, $result->segments[0]->startMs);
        self::assertSame(9000, $result->segments[0]->endMs);
        self::assertSame(62000, $result->segments[2]->startMs);
        self::assertStringNotContainsString('Â', $result->plainText);
    }

    #[Test]
    public function it_accepts_plain_srt_and_vtt_inputs(): void
    {
        $provider = new UserProvidedTranscriptProvider;
        $plain = $provider->parse('A plain transcript without timing.');
        self::assertSame('plain_text', $plain->format);
        self::assertNull($plain->segments[0]->startMs);
        self::assertNotEmpty($plain->warnings);

        $srt = $provider->parse("1\n00:00:01,000 --> 00:00:03,500\nFirst cue\n\n2\n00:00:04,000 --> 00:00:06,000\nSecond cue");
        self::assertSame('srt', $srt->format);
        self::assertSame(1000, $srt->segments[0]->startMs);
        self::assertSame(3500, $srt->segments[0]->endMs);

        $vtt = $provider->parse("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\n<i>Visible cue</i>");
        self::assertSame('vtt', $vtt->format);
        self::assertSame('Visible cue', $vtt->segments[0]->text);
    }

    #[Test]
    public function it_reports_partial_timestamp_input_and_rejects_empty_or_broken_cues(): void
    {
        $provider = new UserProvidedTranscriptProvider;
        $partial = $provider->parse("[0:00] Valid cue\nThis line has no timestamp\n[0:05] Another valid cue");
        self::assertSame('partial', $partial->status);
        self::assertCount(2, $partial->segments);
        self::assertStringContainsString('omitted', implode(' ', $partial->warnings));

        try {
            $provider->parse("WEBVTT\n\nbroken --> cue\n");
            self::fail('Broken timestamp cues should be rejected.');
        } catch (DomainException $exception) {
            self::assertSame('No readable timestamped transcript cues were found.', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $provider->parse(" \n\t ");
    }
}
