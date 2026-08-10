<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\ValueObjects\AnalyzerNavigationContext;
use App\Domain\Analyzer\ValueObjects\YouTubeChannelReference;
use App\Domain\Analyzer\ValueObjects\YouTubeVideoReference;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchlistItem;

final readonly class StartAnalyzerRun
{
    public function __construct(private CreateAnalyzerRun $createAnalyzerRun) {}

    public function handle(
        User $user,
        string $input,
        CollectionCachePolicy $cachePolicy = CollectionCachePolicy::AllowFreshCache,
        string $originKind = 'manual',
        ?string $originReference = null,
        ?string $returnTo = null,
    ): AnalyzerRun {
        return $this->handleTarget($user, 'video', $input, $cachePolicy, $originKind, $originReference, $returnTo);
    }

    public function handleTarget(
        User $user,
        string $targetKind,
        string $input,
        CollectionCachePolicy $cachePolicy = CollectionCachePolicy::AllowFreshCache,
        string $originKind = 'manual',
        ?string $originReference = null,
        ?string $returnTo = null,
    ): AnalyzerRun {
        $requestedOriginKind = $originKind;
        $targetProviderId = $targetKind === 'channel'
            ? YouTubeChannelReference::parse($input)->channelId
            : YouTubeVideoReference::parse($input)->videoId;
        [$originKind, $originReference] = $this->authorizedOrigin(
            $user,
            $targetKind,
            $targetProviderId,
            $originKind,
            $originReference,
        );
        $originRejected = $requestedOriginKind !== 'manual' && $originKind === 'manual';
        $navigationContext = AnalyzerNavigationContext::fromInput($originRejected ? null : $returnTo);
        $run = $this->createAnalyzerRun->handleTarget(
            $user,
            $targetKind,
            $targetProviderId,
            $cachePolicy,
            $originKind,
            $originReference,
            $navigationContext,
        );

        CollectAnalyzerRun::dispatch($run->id)->afterCommit();

        return $run;
    }

    /** @return array{string, string|null} */
    private function authorizedOrigin(
        User $user,
        string $targetKind,
        string $targetProviderId,
        string $originKind,
        ?string $originReference,
    ): array {
        if ($originKind === 'refresh' && $originReference !== null) {
            $ownedSource = AnalyzerRun::query()
                ->where('user_id', $user->id)
                ->where('public_id', $originReference)
                ->where('target_kind', $targetKind)
                ->where('target_provider_id', $targetProviderId)
                ->whereIn('status', ['completed', 'failed'])
                ->exists();

            return $ownedSource ? ['refresh', $originReference] : ['manual', null];
        }

        if ($originReference === null) {
            return in_array($originKind, ['manual', 'explore'], true) && ($originKind !== 'explore' || $this->ownedCanonicalTarget($user, $targetKind, $targetProviderId))
                ? [$originKind, null]
                : ['manual', null];
        }

        $ownedSource = match ($originKind) {
            'search' => ResearchRun::query()
                ->where('user_id', $user->id)->where('public_id', $originReference)
                ->whereHas('videos', fn ($query) => $query->where(
                    $targetKind === 'video' ? 'provider_video_id' : 'channel_id',
                    $targetKind === 'video'
                        ? $targetProviderId
                        : Channel::query()->where('provider_channel_id', $targetProviderId)->value('id'),
                ))->exists(),
            'discover' => $targetKind === 'video' && NicheCandidate::query()
                ->where('public_id', $originReference)
                ->whereHas('discoveryRun', fn ($query) => $query->where('user_id', $user->id))
                ->whereJsonContains('evidence->video_ids', $targetProviderId)->exists(),
            'watchlist' => WatchlistItem::query()->where('user_id', $user->id)->where('public_id', $originReference)
                ->where('target_type', $targetKind)->whereHasMorph('target', [Video::class, Channel::class], fn ($query) => $query->where($targetKind === 'video' ? 'provider_video_id' : 'provider_channel_id', $targetProviderId))->exists(),
            'topic_workspace' => TopicWorkspace::query()->where('user_id', $user->id)->where('public_id', $originReference)
                ->whereHas('items', fn ($items) => $items->where('target_type', $targetKind)->whereHasMorph('target', [Video::class, Channel::class], fn ($query) => $query->where($targetKind === 'video' ? 'provider_video_id' : 'provider_channel_id', $targetProviderId)))->exists(),
            default => false,
        };

        return $ownedSource ? [$originKind, $originReference] : ['manual', null];
    }

    private function ownedCanonicalTarget(User $user, string $targetKind, string $targetProviderId): bool
    {
        return $targetKind === 'video'
            ? Video::query()->where('provider_video_id', $targetProviderId)->where(fn ($query) => $query
                ->whereHas('researchRuns', fn ($runs) => $runs->where('research_runs.user_id', $user->id))
                ->orWhereHas('analyzerRuns', fn ($runs) => $runs->where('user_id', $user->id)))->exists()
            : Channel::query()->where('provider_channel_id', $targetProviderId)->where(fn ($query) => $query
                ->whereHas('videos.researchRuns', fn ($runs) => $runs->where('research_runs.user_id', $user->id))
                ->orWhereHas('analyzerRuns', fn ($runs) => $runs->where('user_id', $user->id)))->exists();
    }
}
