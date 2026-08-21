<?php

namespace App\Http\Controllers\Library;

use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\DeleteProject;
use App\Domain\Library\Actions\SetProjectArchived;
use App\Domain\Library\Actions\UpdateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\ProjectRequest;
use App\Http\ViewModels\LibraryViewModel;
use App\Models\ResearchProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, LibraryViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', ResearchProject::class);

        return Inertia::render('library/projects/index', $viewModel->projectsIndex($request->user(), $request));
    }

    public function show(
        Request $request,
        ResearchProject $researchProject,
        LibraryViewModel $viewModel,
    ): Response {
        Gate::authorize('view', $researchProject);

        return Inertia::render('library/projects/show', $viewModel->projectDetail($request->user(), $researchProject));
    }

    public function store(ProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        Gate::authorize('create', ResearchProject::class);
        $createProject->handle(
            $request->user(),
            (string) $request->validated('name'),
            $this->optionalString($request->validated('description')),
            $this->optionalString($request->validated('color')),
            $this->optionalString($request->validated('purpose')),
            $this->optionalString($request->validated('market_key')),
            $this->optionalString($request->validated('themes')),
            $this->optionalString($request->validated('decision_status')),
            $this->optionalString($request->validated('decision_note')),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('library.projects.index');
    }

    public function update(
        ProjectRequest $request,
        ResearchProject $researchProject,
        UpdateProject $updateProject,
    ): RedirectResponse {
        Gate::authorize('update', $researchProject);
        $updateProject->handle(
            $request->user(),
            $researchProject,
            (string) $request->validated('name'),
            $this->optionalString($request->validated('description')),
            $this->optionalString($request->validated('color')),
            $request->has('purpose') ? $this->optionalString($request->validated('purpose')) : $researchProject->purpose,
            $request->has('market_key') ? $this->optionalString($request->validated('market_key')) : $researchProject->market_key,
            $request->has('themes') ? $this->optionalString($request->validated('themes')) : implode(', ', $researchProject->themes ?? []),
            $request->has('decision_status') ? $this->optionalString($request->validated('decision_status')) : $researchProject->decision_status,
            $request->has('decision_note') ? $this->optionalString($request->validated('decision_note')) : $researchProject->decision_note,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function archive(
        Request $request,
        ResearchProject $researchProject,
        SetProjectArchived $setArchived,
    ): RedirectResponse {
        Gate::authorize('update', $researchProject);
        $setArchived->handle($request->user(), $researchProject, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project archived.')]);

        return back();
    }

    public function restore(
        Request $request,
        ResearchProject $researchProject,
        SetProjectArchived $setArchived,
    ): RedirectResponse {
        Gate::authorize('update', $researchProject);
        $setArchived->handle($request->user(), $researchProject, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project restored.')]);

        return back();
    }

    public function destroy(
        Request $request,
        ResearchProject $researchProject,
        DeleteProject $deleteProject,
    ): RedirectResponse {
        Gate::authorize('delete', $researchProject);
        $deleteProject->handle($request->user(), $researchProject);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('library.projects.index');
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
