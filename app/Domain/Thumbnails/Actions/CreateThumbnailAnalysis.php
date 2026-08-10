<?php

namespace App\Domain\Thumbnails\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Thumbnails\Contracts\ThumbnailAnalysisProvider;
use App\Domain\Thumbnails\Enums\ThumbnailAnalysisStatus;
use App\Jobs\Analyzer\AnalyzeThumbnails;
use App\Models\AnalyzerRun;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateThumbnailAnalysis
{
    public function __construct(private ThumbnailAnalysisProvider $provider) {}

    public function handle(User $user, AnalyzerRun $run): ThumbnailAnalysisProfile
    {
        if ($run->user_id !== $user->id || $run->status !== AnalyzerRunStatus::Completed) {
            throw new DomainException('Thumbnail analysis requires an owned completed Analyzer result.');
        }

        $latest = ThumbnailAnalysisProfile::query()
            ->where('user_id', $user->id)
            ->where('analyzer_run_id', $run->id)
            ->where('provider', $this->provider->name())
            ->where('algorithm_version', $this->provider->version())
            ->latest('attempt_number')
            ->first();
        if ($latest !== null && $latest->status !== ThumbnailAnalysisStatus::Failed) {
            return $latest;
        }

        $profile = DB::transaction(function () use ($user, $run, $latest): ThumbnailAnalysisProfile {
            AnalyzerRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $attempt = $latest === null ? 1 : $latest->attempt_number + 1;

            return ThumbnailAnalysisProfile::query()->firstOrCreate([
                'analyzer_run_id' => $run->id,
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
                'attempt_number' => $attempt,
            ], [
                'user_id' => $user->id,
                'status' => ThumbnailAnalysisStatus::Queued,
                'provenance' => 'inferred',
                'calculation_version' => (string) config('thumbnails.calculation_version', 'thumbnail-performance-association-v1'),
                'minimum_sample_size' => max(2, min(10, (int) config('thumbnails.minimum_sample_size', 2))),
                'cohort_video_count' => $run->videoMemberships()->where('role', 'channel_recent_upload')->count(),
            ]);
        });

        AnalyzeThumbnails::dispatch($profile->id);

        return $profile;
    }
}
