<?php

namespace App\Domain\Library\Services;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;

class ResolveLibraryTarget
{
    public function handle(User $user, LibraryTargetType $type, string $reference): Model
    {
        return match ($type) {
            LibraryTargetType::NicheCandidate => NicheCandidate::query()
                ->where('public_id', $reference)
                ->whereHas('discoveryRun', fn ($query) => $query->where('user_id', $user->id))
                ->firstOrFail(),
            LibraryTargetType::ResearchQuery => ResearchQuery::query()
                ->where('public_id', $reference)
                ->where('user_id', $user->id)
                ->firstOrFail(),
            LibraryTargetType::ResearchRun => ResearchRun::query()
                ->where('public_id', $reference)
                ->where('user_id', $user->id)
                ->firstOrFail(),
            LibraryTargetType::Video => Video::query()
                ->where('provider_video_id', $reference)
                ->where(function ($query) use ($user): void {
                    $query->whereHas('researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
                        ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id));
                })
                ->firstOrFail(),
            LibraryTargetType::Channel => Channel::query()
                ->where('provider_channel_id', $reference)
                ->where(function ($query) use ($user): void {
                    $query->whereHas('videos.researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
                        ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id));
                })
                ->firstOrFail(),
        };
    }
}
