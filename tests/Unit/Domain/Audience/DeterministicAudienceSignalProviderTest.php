<?php

namespace Tests\Unit\Domain\Audience;

use App\Domain\Audience\Data\AudienceSignalInput;
use App\Domain\Audience\Services\DeterministicAudienceSignalProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeterministicAudienceSignalProviderTest extends TestCase
{
    #[Test]
    public function it_derives_versioned_repeated_multilingual_signal_kinds_with_evidence(): void
    {
        $provider = new DeterministicAudienceSignalProvider;
        $result = $provider->analyze([
            new AudienceSignalInput(11, 'How can I fix the Audio problem? Please make an Audio guide.'),
            new AudienceSignalInput(12, 'How can I fix this Audio problem? The Audio is unclear.'),
            new AudienceSignalInput(13, 'Please make another Audio tutorial because the Audio problem is confusing.'),
            new AudienceSignalInput(14, 'The Audio problem does not work for me.'),
        ]);

        self::assertSame('complete', $result->status);
        self::assertSame('en', $result->language);
        self::assertSame('audience-comment-terms-v1', $provider->version());
        self::assertNotNull($result->confidenceScore);
        self::assertContains('topic', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains('entity', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains('repeated_question', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains('suggestion', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains('complaint', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains('confusion_point', array_map(fn ($signal): string => $signal->kind, $result->signals));
        self::assertContains(11, collect($result->signals)->flatMap(fn ($signal): array => $signal->evidenceCommentIds)->all());
    }

    #[Test]
    public function it_handles_sparse_mixed_language_and_unsafe_samples_without_echoing_identifiers(): void
    {
        $provider = new DeterministicAudienceSignalProvider;
        $sparse = $provider->analyze([
            new AudienceSignalInput(1, 'Only one comment'),
            new AudienceSignalInput(2, 'Only two comments'),
        ]);
        self::assertSame('insufficient', $sparse->status);
        self::assertSame([], $sparse->signals);

        $unsafe = $provider->analyze([
            new AudienceSignalInput(1, 'Email person@example.com about private details'),
            new AudienceSignalInput(2, 'Call +1 202 555 0199 for private details'),
            new AudienceSignalInput(3, 'Visit https://example.com/private for private details'),
        ]);
        self::assertSame('unsafe', $unsafe->status);
        self::assertSame([], $unsafe->signals);
        self::assertStringNotContainsString('person@example.com', implode(' ', $unsafe->warnings));

        $mixed = $provider->analyze([
            new AudienceSignalInput(1, 'Audio problem needs an audio guide'),
            new AudienceSignalInput(2, 'Problemă audio are nevoie de ghid audio'),
            new AudienceSignalInput(3, 'Проблема audio требует audio guide'),
            new AudienceSignalInput(4, 'Audio problem needs an audio tutorial'),
        ]);
        self::assertSame('partial', $mixed->status);
        self::assertStringContainsString('mixed detected languages', implode(' ', $mixed->warnings));
    }
}
