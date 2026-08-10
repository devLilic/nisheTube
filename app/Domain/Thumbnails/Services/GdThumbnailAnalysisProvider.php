<?php

namespace App\Domain\Thumbnails\Services;

use App\Domain\Thumbnails\Contracts\ThumbnailAnalysisProvider;
use App\Domain\Thumbnails\Data\ThumbnailFeatures;
use App\Domain\Thumbnails\Data\ThumbnailImagePayload;
use App\Domain\Thumbnails\Exceptions\ThumbnailImageException;
use GdImage;

final class GdThumbnailAnalysisProvider implements ThumbnailAnalysisProvider
{
    public function name(): string
    {
        return 'gd_visual_features';
    }

    public function version(): string
    {
        return (string) config('thumbnails.provider_version', 'thumbnail-visual-features-v1');
    }

    public function analyze(ThumbnailImagePayload $image): ThumbnailFeatures
    {
        $source = @imagecreatefromstring($image->bytes);
        if (! $source instanceof GdImage) {
            throw new ThumbnailImageException('thumbnail_decode_failed', 'The thumbnail image could not be decoded.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            if ($width < 16 || $height < 16 || $width > 8_192 || $height > 8_192) {
                throw new ThumbnailImageException('thumbnail_invalid_dimensions', 'The thumbnail dimensions are outside the safe analysis range.');
            }

            $sampleWidth = min(64, $width);
            $sampleHeight = max(1, (int) round($height * ($sampleWidth / $width)));
            $sampleHeight = min(64, $sampleHeight);
            $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
            if (! $sample instanceof GdImage) {
                throw new ThumbnailImageException('thumbnail_analysis_failed', 'A thumbnail analysis sample could not be created.');
            }
            imagecopyresampled($sample, $source, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);

            try {
                [$brightness, $saturation, $contrast, $edgeDensity, $dominantColor] = $this->measure($sample);
            } finally {
                imagedestroy($sample);
            }

            $brightnessClass = $brightness < 35 ? 'dark' : ($brightness < 70 ? 'balanced' : 'bright');
            $saturationClass = $saturation < 30 ? 'muted' : ($saturation < 65 ? 'balanced' : 'vivid');
            $contrastClass = $contrast < 20 ? 'low contrast' : ($contrast < 45 ? 'medium contrast' : 'high contrast');
            $compositionClass = $edgeDensity < 15 ? 'minimal detail' : ($edgeDensity < 35 ? 'balanced detail' : 'dense detail');
            $clusterKey = implode('|', [$dominantColor, $brightnessClass, $compositionClass]);
            $confidence = round(min(96, 72 + min(16, ($sampleWidth * $sampleHeight) / 128)), 4);

            return new ThumbnailFeatures(
                $width,
                $height,
                round($width / $height, 4),
                $brightness,
                $saturation,
                $contrast,
                $edgeDensity,
                $dominantColor,
                $brightnessClass,
                $saturationClass,
                $contrastClass,
                $compositionClass,
                $clusterKey,
                $confidence,
            );
        } finally {
            imagedestroy($source);
        }
    }

    /** @return array{float, float, float, float, string} */
    private function measure(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $luminances = [];
        $saturations = [];
        $hues = array_fill_keys(['red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple', 'magenta'], 0);
        $edges = 0;
        $edgeComparisons = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                $luminance = (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
                $luminances[] = $luminance;
                [$hue, $saturation] = $this->hueAndSaturation($red, $green, $blue);
                $saturations[] = $saturation * 100;
                if ($saturation >= 0.15) {
                    $hues[$this->hueFamily($hue)]++;
                }

                if ($x > 0) {
                    $left = $luminances[count($luminances) - 2];
                    $edges += abs($luminance - $left) >= 25 ? 1 : 0;
                    $edgeComparisons++;
                }
                if ($y > 0) {
                    $aboveIndex = count($luminances) - 1 - $width;
                    $edges += abs($luminance - $luminances[$aboveIndex]) >= 25 ? 1 : 0;
                    $edgeComparisons++;
                }
            }
        }

        $count = count($luminances);
        $mean = array_sum($luminances) / $count;
        $variance = array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $luminances)) / $count;
        arsort($hues, SORT_NUMERIC);
        $dominantHue = (string) array_key_first($hues);
        $coloredPixels = array_sum($hues);

        return [
            round(($mean / 255) * 100, 4),
            round(array_sum($saturations) / $count, 4),
            round(min(100, (sqrt($variance) / 127.5) * 100), 4),
            round(($edges / $edgeComparisons) * 100, 4),
            $coloredPixels / $count < 0.2 ? 'neutral' : $dominantHue,
        ];
    }

    /** @return array{float, float} */
    private function hueAndSaturation(int $red, int $green, int $blue): array
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $saturation = $max <= 0.0 ? 0.0 : $delta / $max;
        if ($delta <= 0.0) {
            return [0.0, $saturation];
        }
        $hue = match ($max) {
            $r => 60 * fmod((($g - $b) / $delta), 6),
            $g => 60 * ((($b - $r) / $delta) + 2),
            default => 60 * ((($r - $g) / $delta) + 4),
        };

        return [$hue < 0 ? $hue + 360 : $hue, $saturation];
    }

    private function hueFamily(float $hue): string
    {
        return match (true) {
            $hue < 15 || $hue >= 345 => 'red',
            $hue < 45 => 'orange',
            $hue < 75 => 'yellow',
            $hue < 165 => 'green',
            $hue < 195 => 'cyan',
            $hue < 255 => 'blue',
            $hue < 285 => 'purple',
            default => 'magenta',
        };
    }
}
