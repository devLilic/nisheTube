<?php

namespace App\Domain\Navigation\Services;

use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\DiscoveryRun;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;

/** @phpstan-type SearchItem array{kind: string, title: string, description: string, meta: string, href: string} */
final class GlobalResearchSearch
{
    private const GROUP_LIMIT = 5;

    private const TOTAL_LIMIT = 20;

    /** @return array{query: string, items: list<SearchItem>, total: int, truncated: bool} */
    public function search(User $user, string $query): array
    {
        $like = '%'.addcslashes($query, '%_\\').'%';

        $items = collect()
            ->concat($this->themes($user, $like))
            ->concat($this->videos($user, $like))
            ->concat($this->channels($user, $like))
            ->concat($this->runs($user, $like));

        $total = $items->count();

        return [
            'query' => $query,
            'items' => array_values($items->take(self::TOTAL_LIMIT)->all()),
            'total' => min($total, self::TOTAL_LIMIT),
            'truncated' => $total > self::TOTAL_LIMIT,
        ];
    }

    /** @return list<SearchItem> */
    private function themes(User $user, string $like): array
    {
        return array_values(NicheCandidate::query()
            ->whereHas('discoveryRun', fn (Builder $builder): Builder => $builder->where('user_id', $user->id))
            ->where(fn (Builder $builder): Builder => $builder
                ->where('phrase', 'like', $like)
                ->orWhere('summary', 'like', $like))
            ->latest('updated_at')
            ->limit(self::GROUP_LIMIT)
            ->get(['public_id', 'phrase', 'summary', 'status', 'updated_at'])
            ->map(fn (NicheCandidate $candidate): array => [
                'kind' => 'theme',
                'title' => $candidate->phrase,
                'description' => $candidate->summary ?: 'Stored discovery theme',
                'meta' => ucfirst(str_replace('_', ' ', $candidate->status->value)),
                'href' => '/discover',
            ])->values()->all());
    }

    /** @return list<SearchItem> */
    private function videos(User $user, string $like): array
    {
        $ownedAnalyzerIds = AnalyzerRun::query()->select('video_id')->where('user_id', $user->id)->whereNotNull('video_id');
        $ownedResearchIds = \DB::table('research_run_videos')
            ->join('research_runs', 'research_runs.id', '=', 'research_run_videos.research_run_id')
            ->where('research_runs.user_id', $user->id)
            ->select('research_run_videos.video_id');

        return array_values(Video::query()
            ->where(fn (Builder $builder): Builder => $builder
                ->whereIn('videos.id', $ownedAnalyzerIds)
                ->orWhereIn('videos.id', $ownedResearchIds))
            ->where(fn (Builder $builder): Builder => $builder
                ->where('title', 'like', $like)
                ->orWhere('provider_video_id', 'like', $like))
            ->orderByDesc('updated_at')
            ->limit(self::GROUP_LIMIT)
            ->get(['id', 'provider_video_id', 'title', 'published_at'])
            ->map(fn (Video $video): array => [
                'kind' => 'video',
                'title' => $video->title ?: 'Stored video',
                'description' => $video->provider_video_id,
                'meta' => 'Video',
                'href' => '/analyzer?video='.$video->provider_video_id,
            ])->values()->all());
    }

    /** @return list<SearchItem> */
    private function channels(User $user, string $like): array
    {
        $ownedAnalyzerIds = AnalyzerRun::query()->select('channel_id')->where('user_id', $user->id)->whereNotNull('channel_id');
        $ownedResearchIds = \DB::table('research_run_videos')
            ->join('research_runs', 'research_runs.id', '=', 'research_run_videos.research_run_id')
            ->join('videos', 'videos.id', '=', 'research_run_videos.video_id')
            ->where('research_runs.user_id', $user->id)
            ->select('videos.channel_id');

        return array_values(Channel::query()
            ->where(fn (Builder $builder): Builder => $builder
                ->whereIn('channels.id', $ownedAnalyzerIds)
                ->orWhereIn('channels.id', $ownedResearchIds))
            ->where(fn (Builder $builder): Builder => $builder
                ->where('title', 'like', $like)
                ->orWhere('provider_channel_id', 'like', $like))
            ->orderByDesc('updated_at')
            ->limit(self::GROUP_LIMIT)
            ->get(['provider_channel_id', 'title', 'country'])
            ->map(fn (Channel $channel): array => [
                'kind' => 'channel',
                'title' => $channel->title ?: 'Stored channel',
                'description' => $channel->provider_channel_id,
                'meta' => $channel->country ? 'Channel · '.$channel->country : 'Channel',
                'href' => '/analyzer?channel='.$channel->provider_channel_id,
            ])->values()->all());
    }

    /** @return list<SearchItem> */
    private function runs(User $user, string $like): array
    {
        $research = ResearchRun::query()
            ->where('user_id', $user->id)
            ->where('query_text', 'like', $like)
            ->latest()
            ->limit(self::GROUP_LIMIT)
            ->get()
            ->map(fn (ResearchRun $run): array => [
                'kind' => 'run', 'title' => $run->query_text, 'description' => 'Research run',
                'meta' => ucfirst($run->status->value).' · '.$run->market_key,
                'href' => '/research/runs/'.$run->public_id, '_sort' => $run->created_at?->getTimestamp() ?? 0,
            ]);
        $analyzer = AnalyzerRun::query()
            ->where('user_id', $user->id)
            ->where('target_provider_id', 'like', $like)
            ->latest()
            ->limit(self::GROUP_LIMIT)
            ->get()
            ->map(fn (AnalyzerRun $run): array => [
                'kind' => 'run', 'title' => $run->target_provider_id, 'description' => ucfirst($run->target_kind).' Analyzer run',
                'meta' => ucfirst($run->status->value), 'href' => '/analyzer/runs/'.$run->public_id,
                '_sort' => $run->created_at?->getTimestamp() ?? 0,
            ]);
        $discovery = DiscoveryRun::query()
            ->where('user_id', $user->id)
            ->where(fn (Builder $builder): Builder => $builder
                ->where('market_key', 'like', $like)
                ->orWhereHas('seeds', fn (Builder $seed): Builder => $seed->where('seed_query', 'like', $like)))
            ->latest()
            ->limit(self::GROUP_LIMIT)
            ->get()
            ->map(fn (DiscoveryRun $run): array => [
                'kind' => 'run', 'title' => 'Discovery · '.$run->market_key, 'description' => 'Discovery run',
                'meta' => ucfirst($run->status->value), 'href' => '/discover/runs/'.$run->public_id,
                '_sort' => $run->created_at?->getTimestamp() ?? 0,
            ]);

        return array_values($research->concat($analyzer)->concat($discovery)
            ->sortByDesc('_sort')->take(self::GROUP_LIMIT)->values()
            ->map(function (array $item): array {
                unset($item['_sort']);

                return $item;
            })
            ->all());
    }
}
