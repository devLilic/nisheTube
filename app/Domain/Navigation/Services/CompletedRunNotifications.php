<?php

namespace App\Domain\Navigation\Services;

use App\Models\AnalyzerRun;
use App\Models\DiscoveryRun;
use App\Models\ResearchRun;
use App\Models\User;
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
        $research = ResearchRun::query()->where('user_id', $user->id)->whereNotNull('completed_at')->latest('completed_at')->limit(self::LIMIT)->get()
            ->map(fn (ResearchRun $run): array => $this->item('Research completed', $run->query_text, '/research/runs/'.$run->public_id, $run->completed_at, $readAt));
        $analyzer = AnalyzerRun::query()->where('user_id', $user->id)->whereNotNull('completed_at')->latest('completed_at')->limit(self::LIMIT)->get()
            ->map(fn (AnalyzerRun $run): array => $this->item('Analyzer completed', $run->target_provider_id, '/analyzer/runs/'.$run->public_id, $run->completed_at, $readAt));
        $discovery = DiscoveryRun::query()->where('user_id', $user->id)->whereNotNull('completed_at')->latest('completed_at')->limit(self::LIMIT)->get()
            ->map(fn (DiscoveryRun $run): array => $this->item('Discovery completed', $run->market_key, '/discover/runs/'.$run->public_id, $run->completed_at, $readAt));

        /** @var Collection<int, NotificationItem> $items */
        $items = $research->concat($analyzer)->concat($discovery)->sortByDesc('completed_at')->take(self::LIMIT)->values();

        return ['unread_count' => $items->where('unread', true)->count(), 'items' => array_values($items->all())];
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
