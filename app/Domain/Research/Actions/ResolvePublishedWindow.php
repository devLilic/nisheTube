<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\PublishedWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ResolvePublishedWindow
{
    /** @return array{CarbonInterface|null, CarbonInterface|null} */
    public function handle(
        PublishedWindow $window,
        string $timezone,
        ?string $customAfter = null,
        ?string $customBefore = null,
    ): array {
        if ($window === PublishedWindow::Any) {
            return [null, null];
        }

        if ($window === PublishedWindow::Custom) {
            if ($customAfter === null || $customBefore === null) {
                throw new InvalidArgumentException('Custom published windows require both boundary dates.');
            }

            return [
                CarbonImmutable::createFromFormat('!Y-m-d', $customAfter, $timezone)->startOfDay()->utc(),
                CarbonImmutable::createFromFormat('!Y-m-d', $customBefore, $timezone)->endOfDay()->utc(),
            ];
        }

        $now = CarbonImmutable::now($timezone);
        $days = $window->lookbackDays();

        if ($days === null) {
            throw new InvalidArgumentException('Unsupported published window.');
        }

        return [$now->subDays($days)->utc(), $now->utc()];
    }
}
