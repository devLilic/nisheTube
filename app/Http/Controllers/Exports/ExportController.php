<?php

namespace App\Http\Controllers\Exports;

use App\Domain\Exports\Actions\CreateResearchExport;
use App\Domain\Exports\Actions\DeleteResearchExport;
use App\Domain\Exports\Actions\RetryResearchExport;
use App\Domain\Exports\Enums\ExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exports\StoreResearchExportRequest;
use App\Http\ViewModels\ExportViewModel;
use App\Models\ResearchExport;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController extends Controller
{
    public function index(Request $request, ExportViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', ResearchExport::class);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('exports/index', [
            'builder' => $viewModel->builder($user),
            'jobs' => Inertia::defer(fn (): array => $viewModel->jobs($user), rescue: true),
        ]);
    }

    public function store(StoreResearchExportRequest $request, CreateResearchExport $create): RedirectResponse
    {
        Gate::authorize('create', ResearchExport::class);

        try {
            $create->handle($request->user(), $request->exportFormat(), $request->researchRunIds(), $request->columns());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['research_run_ids' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export queued.')]);

        return to_route('exports.index');
    }

    public function download(ResearchExport $researchExport): StreamedResponse
    {
        Gate::authorize('download', $researchExport);
        abort_if($researchExport->status !== ExportStatus::Completed, 409, 'The export is not ready.');
        abort_if($researchExport->expires_at?->isPast() ?? false, 410, 'The export has expired.');
        abort_if($researchExport->disk === null || $researchExport->path === null, 404);
        abort_unless(Storage::disk($researchExport->disk)->exists($researchExport->path), 404);

        return Storage::disk($researchExport->disk)->download(
            $researchExport->path,
            'nishetube-export-'.$researchExport->created_at?->format('Ymd-His').'.'.$researchExport->format->extension(),
        );
    }

    public function retry(Request $request, ResearchExport $researchExport, RetryResearchExport $retry): RedirectResponse
    {
        Gate::authorize('retry', $researchExport);
        /** @var User $user */
        $user = $request->user();

        try {
            $retry->handle($user, $researchExport);
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export queued again.')]);

        return to_route('exports.index');
    }

    public function destroy(Request $request, ResearchExport $researchExport, DeleteResearchExport $delete): RedirectResponse
    {
        Gate::authorize('delete', $researchExport);
        /** @var User $user */
        $user = $request->user();
        $delete->handle($user, $researchExport);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export deleted.')]);

        return to_route('exports.index');
    }
}
