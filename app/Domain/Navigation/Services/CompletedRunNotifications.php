<?php

namespace App\Domain\Navigation\Services;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\AnalyzerRun;
use App\Models\DiscoveryRun;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\WatchlistRefreshRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** @phpstan-type NotificationItem array{title: string, description: string, href: string, completed_at: string, unread: bool} */
final class CompletedRunNotifications
{
    private const LIMIT = 8;

    /** @return array{unread_count: int, items: list<NotificationItem>} */
    public function for(User $user): array
    {
        $readAt = $user->completed_run_notifications_read_at;
        $research = ResearchRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ResearchRunStatus::Completed, ResearchRunStatus::Failed, ResearchRunStatus::Cancelled])
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ResearchRun $run): array => $this->researchItem($run, $readAt));
        $analyzer = AnalyzerRun::query()->where('user_id', $user->id)->where('origin_kind', '!=', 'watchlist')->whereNotNull('completed_at')->latest('completed_at')->limit(self::LIMIT)->get()
            ->map(fn (AnalyzerRun $run): array => $this->item('Analyzer completed', $run->target_provider_id, '/analyzer/runs/'.$run->public_id, $run->completed_at, $readAt));
        $discovery = DiscoveryRun::query()->where('user_id', $user->id)->whereNotNull('completed_at')->latest('completed_at')->limit(self::LIMIT)->get()
            ->map(fn (DiscoveryRun $run): array => $this->item('Discovery completed', $run->market_key, '/discover/runs/'.$run->public_id, $run->completed_at, $readAt));
        $watchlist = WatchlistRefreshRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'partial', 'failed'])
            ->whereHas('item', fn ($items) => $items->where('user_id', $user->id)->where('notify_on_refresh', true))
            ->with('item.target')
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (WatchlistRefreshRun $refresh): array => $this->watchlistItem($refresh, $readAt));

        /** @var Collection<int, NotificationItem> $items */
        $items = $research->concat($analyzer)->concat($discovery)->concat($watchlist)->sortByDesc('completed_at')->take(self::LIMIT)->values();

        return ['unread_count' => $items->where('unread', true)->count(), 'items' => array_values($items->all())];
    }

    /** @return NotificationItem */
    private function watchlistItem(WatchlistRefreshRun $refresh, ?CarbonInterface $readAt): array
    {
        $status = $refresh->status->value;
        $title = match ($status) {
            'completed' => 'Watchlist observation ready',
            'partial' => 'Watchlist observation has partial data',
            default => 'Watchlist refresh needs attention',
        };
        $label = $refresh->item->target?->getAttribute('title') ?? 'Unavailable watched subject';
        $description = match ($status) {
            'partial' => $label.' — some metrics are unavailable.',
            'failed' => $label.' — '.($refresh->error_message ?? 'The previous observation is preserved and the refresh can be retried.'),
            default => $label,
        };

        return $this->item($title, $description, '/watchlist', $refresh->completed_at ?? $refresh->failed_at ?? $refresh->updated_at, $readAt);
    }

    /** @return NotificationItem */
    private function researchItem(ResearchRun $run, ?CarbonInterface $readAt): array
    {
        [$title, $description, $occurredAt] = match ($run->status) {
            ResearchRunStatus::Failed => [
                'Research needs attention',
                $run->query_text.' — '.($run->error_message ?? 'The run stopped and can be retried as a new attempt.'),
                $run->failed_at,
            ],
            ResearchRunStatus::Cancelled => [
                'Queued research cancelled',
                $run->query_text.' — no YouTube requests were made.',
                $run->failed_at,
            ],
            default => ['Research completed', $run->query_text, $run->completed_at],
        };

        return $this->item(
            $title,
            $description,
            '/research/runs/'.$run->public_id,
            $occurredAt ?? $run->updated_at,
            $readAt,
        );
    }

    /** @return NotificationItem */
    private function item(
        string $title,
        string $description,
        string $href,
        ?CarbonInterface $completedAt,
        ?CarbonInterface $readAt,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'href' => $href,
            'completed_at' => $completedAt?->toIso8601String() ?? '',
            'unread' => $completedAt !== null && ($readAt === null || $completedAt->isAfter($readAt)),
        ];
    }
}
