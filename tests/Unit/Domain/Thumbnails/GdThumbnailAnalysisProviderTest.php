<?php

namespace Tests\Unit\Domain\Thumbnails;

use App\Domain\Thumbnails\Data\ThumbnailImagePayload;
use App\Domain\Thumbnails\Exceptions\ThumbnailImageException;
use App\Domain\Thumbnails\Services\GdThumbnailAnalysisProvider;
use Tests\TestCase;

final class GdThumbnailAnalysisProviderTest extends TestCase
{
    public function test_it_extracts_deterministic_versioned_features_without_retaining_image_bytes(): void
    {
        $image = imagecreatetruecolor(32, 16);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocate($image, 20, 20, 20));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $provider = new GdThumbnailAnalysisProvider;
        $payload = new ThumbnailImagePayload($bytes, 'image/png', hash('sha256', $bytes));
        $first = $provider->analyze($payload);
        $second = $provider->analyze($payload);

        self::assertSame('gd_visual_features', $provider->name());
        self::assertSame('thumbnail-visual-features-v1', $provider->version());
        self::assertEquals($first, $second);
        self::assertSame(32, $first->width);
        self::assertSame(16, $first->height);
        self::assertSame(2.0, $first->aspectRatio);
        self::assertSame('red', $first->dominantColor);
        self::assertStringContainsString('red|', $first->clusterKey);
        self::assertGreaterThan(0, $first->edgeDensity);
    }

    public function test_it_rejects_undecodable_image_bytes_with_a_safe_code(): void
    {
        $this->expectException(ThumbnailImageException::class);
        $this->expectExceptionMessage('could not be decoded');

        (new GdThumbnailAnalysisProvider)->analyze(
            new ThumbnailImagePayload('not-an-image', 'image/png', hash('sha256', 'not-an-image')),
        );
    }

    public function test_it_handles_fully_black_achromatic_pixels_without_dividing_by_zero(): void
    {
        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $features = (new GdThumbnailAnalysisProvider)->analyze(
            new ThumbnailImagePayload($bytes, 'image/png', hash('sha256', $bytes)),
        );

        self::assertSame(0.0, $features->averageBrightness);
        self::assertSame(0.0, $features->averageSaturation);
        self::assertSame('neutral', $features->dominantColor);
        self::assertSame('neutral|dark|minimal detail', $features->clusterKey);
    }
}
