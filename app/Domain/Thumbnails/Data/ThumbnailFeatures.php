<?php

namespace App\Domain\Thumbnails\Data;

final readonly class ThumbnailFeatures
{
    public function __construct(
        public int $width,
        public int $height,
        public float $aspectRatio,
        public float $averageBrightness,
        public float $averageSaturation,
        public float $contrastScore,
        public float $edgeDensity,
        public string $dominantColor,
        public string $brightnessClass,
        public string $saturationClass,
        public string $contrastClass,
        public string $compositionClass,
        public string $clusterKey,
        public float $confidenceScore,
    ) {}
}
