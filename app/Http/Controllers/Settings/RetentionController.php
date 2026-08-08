<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Enums\CleanupMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeleteResearchSnapshotsRequest;
use App\Http\Requests\Settings\StoreRetentionCleanupRequest;
use App\Http\ViewModels\RetentionViewModel;
use App\Models\CleanupRun;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RetentionController extends Controller
{
    public function index(Request $request, RetentionViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', CleanupRun::class);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/retention', [
            'retention' => Inertia::defer(
                fn (): array => $viewModel->workspace($user),
                rescue: true,
            ),
        ]);
    }

    public function preview(Request $request, CreateCleanupRun $cleanup): RedirectResponse
    {
        Gate::authorize('create', CleanupRun::class);
        $cleanup->handle(
            $request->user(),
            CleanupMode::ManualRetention,
            dryRun: true,
            initiator: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Retention preview recorded. No data was deleted.']);

        return to_route('retention.index');
    }

    public function cleanup(StoreRetentionCleanupRequest $request, CreateCleanupRun $cleanup): RedirectResponse
    {
        Gate::authorize('create', CleanupRun::class);
        $cleanup->handle(
            $request->user(),
            CleanupMode::ManualRetention,
            dryRun: false,
            initiator: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Eligible retention cleanup was queued. Favorited runs remain preserved.']);

        return to_route('retention.index');
    }

    public function destroy(DeleteResearchSnapshotsRequest $request, CreateCleanupRun $cleanup): RedirectResponse
    {
        Gate::authorize('create', CleanupRun::class);

        try {
            $cleanup->handle(
                $request->user(),
                CleanupMode::ManualSelection,
                dryRun: false,
                selectedRunPublicIds: $request->researchRunIds(),
                favoriteImpactConfirmed: $request->boolean('favorite_impact_confirmed'),
                initiator: $request->user(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'favorite_impact_confirmed' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Selected snapshot deletion was queued.']);

        return to_route('retention.index');
    }
}
