<?php

namespace App\Domain\Audience\Actions;

use App\Models\AnalyzerRun;
use App\Models\AudienceSignal;
use App\Models\AudienceSignalExclusion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

final class ManageAudienceSignalExclusion
{
    public function exclude(User $user, AnalyzerRun $run, string $word): AudienceSignalExclusion
    {
        $normalized = $this->normalize($word);

        if ($run->user_id !== $user->id) {
            throw new DomainException('You cannot manage exclusions for this Analyzer result.');
        }

        $existsOnRun = AudienceSignal::query()
            ->where('label_key', $normalized)
            ->whereHas('profile', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('analyzer_run_id', $run->id))
            ->exists();

        if (! $existsOnRun) {
            throw new DomainException('The selected word is not an Audience Signal on this Analyzer result.');
        }

        $exclusion = AudienceSignalExclusion::query()->firstOrNew([
            'user_id' => $user->id,
            'normalized_word' => $normalized,
        ]);

        if ($exclusion->exists && $exclusion->is_active) {
            return $exclusion;
        }

        $exclusion->fill([
            'display_word' => trim($word),
            'is_active' => true,
            'excluded_at' => now(),
            'restored_at' => null,
        ])->save();

        return $exclusion;
    }

    public function restore(User $user, AudienceSignalExclusion $exclusion): AudienceSignalExclusion
    {
        if ($exclusion->user_id !== $user->id) {
            throw new DomainException('You cannot restore another user\'s excluded word.');
        }

        if (! $exclusion->is_active) {
            return $exclusion;
        }

        $exclusion->update([
            'is_active' => false,
            'restored_at' => now(),
        ]);

        return $exclusion;
    }

    private function normalize(string $word): string
    {
        $trimmed = trim($word);
        if ($trimmed === '' || mb_strlen($trimmed) > 64 || preg_match('/^[\p{L}\p{N}][\p{L}\p{N}-]*$/u', $trimmed) !== 1) {
            throw new DomainException('Only one word can be excluded at a time.');
        }

        return Str::lower($trimmed);
    }
}
