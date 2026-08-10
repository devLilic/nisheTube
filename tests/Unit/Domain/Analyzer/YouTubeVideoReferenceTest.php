<?php

namespace Tests\Unit\Domain\Analyzer;

use App\Domain\Analyzer\ValueObjects\YouTubeVideoReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class YouTubeVideoReferenceTest extends TestCase
{
    #[DataProvider('supportedReferences')]
    public function test_supported_references_resolve_to_one_canonical_video_id(string $input): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTubeVideoReference::parse($input)->videoId);
    }

    /** @return iterable<string, array{string}> */
    public static function supportedReferences(): iterable
    {
        yield 'raw ID' => ['dQw4w9WgXcQ'];
        yield 'watch URL' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'mobile watch URL' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=10'];
        yield 'short URL' => ['https://youtu.be/dQw4w9WgXcQ'];
        yield 'Shorts URL' => ['https://youtube.com/shorts/dQw4w9WgXcQ'];
        yield 'embed URL' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'];
    }

    #[DataProvider('invalidReferences')]
    public function test_ambiguous_and_malicious_references_are_rejected(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        YouTubeVideoReference::parse($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'short ID' => ['abc'];
        yield 'lookalike host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'];
        yield 'userinfo trick' => ['https://youtube.com@evil.test/watch?v=dQw4w9WgXcQ'];
        yield 'unexpected path' => ['https://youtube.com/channel/dQw4w9WgXcQ'];
        yield 'ambiguous video parameters' => ['https://youtube.com/watch?v=dQw4w9WgXcQ&v=abcdefghijk'];
        yield 'extra short-link segment' => ['https://youtu.be/dQw4w9WgXcQ/extra'];
        yield 'encoded invalid ID' => ['https://youtu.be/%2Fetc%2Fpasswd'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
    }
}
