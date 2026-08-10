<?php

namespace App\Domain\Analyzer\Actions;

use App\Models\Video;
use App\Models\VideoCategory;

final class ResolveVideoCategory
{
    public function handle(Video $video): ?VideoCategory
    {
        if ($video->category_id === null) {
            return null;
        }

        $name = config("analyzer.categories.{$video->category_id}");

        if (! is_string($name) || $name === '') {
            return null;
        }

        return VideoCategory::query()->firstOrCreate([
            'provider' => $video->provider,
            'category_id' => $video->category_id,
            'region_key' => 'global',
            'display_language' => 'en',
        ], [
            'name' => $name,
            'assignable' => true,
            'fetched_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
