<?php

namespace App\Http\Middleware;

use App\Domain\Localization\Enums\UiLocale;
use App\Domain\Navigation\Services\CompletedRunNotifications;
use App\Domain\Settings\Services\ResearchContextResolver;
use App\Domain\YouTube\Contracts\QuotaLedger;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $researchContext = null;
        if ($request->user() !== null) {
            $resolved = app(ResearchContextResolver::class)->resolve(
                $request->user(),
                $request->session()->get(ResearchContextResolver::SESSION_KEY),
            );
            $request->session()->put(ResearchContextResolver::SESSION_KEY, $resolved['stored']);
            $researchContext = $resolved['shared'];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'fallbackLocale' => UiLocale::English->value,
            'supportedLocales' => UiLocale::values(),
            'auth' => [
                'user' => $request->user(),
            ],
            'researchContext' => $researchContext,
            'completedRunNotifications' => fn (): ?array => $request->user() === null
                ? null
                : app(CompletedRunNotifications::class)->for($request->user()),
            'youtubeQuota' => fn (): ?array => $request->user() === null
                ? null
                : app(QuotaLedger::class)->summary()->toSafeArray(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
