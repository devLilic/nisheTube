<?php

namespace App\Http\Controllers\Ideas;

use App\Domain\Comments\Actions\RemoveCommentIdea;
use App\Domain\Comments\Actions\SaveCommentIdea;
use App\Domain\Comments\ReadModels\BuildSavedCommentIdeasIndex;
use App\Http\Controllers\Controller;
use App\Models\PublicComment;
use App\Models\SavedCommentIdea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SavedCommentIdeaController extends Controller
{
    public function index(Request $request, BuildSavedCommentIdeasIndex $view): Response
    {
        Gate::authorize('viewAny', SavedCommentIdea::class);

        return Inertia::render('ideas/index', $view->handle($request->user()));
    }

    public function store(Request $request, PublicComment $publicComment, SaveCommentIdea $save): RedirectResponse
    {
        Gate::authorize('create', SavedCommentIdea::class);
        $idea = $save->handle($request->user(), $publicComment);

        Inertia::flash('toast', [
            'type' => $idea->wasRecentlyCreated ? 'success' : 'info',
            'message' => $idea->wasRecentlyCreated ? __('Comment saved to Ideas.') : __('Comment is already saved in Ideas.'),
        ]);

        return back();
    }

    public function destroy(Request $request, SavedCommentIdea $savedCommentIdea, RemoveCommentIdea $remove): RedirectResponse
    {
        Gate::authorize('delete', $savedCommentIdea);
        $remove->handle($request->user(), $savedCommentIdea);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment removed from Ideas.')]);

        return back();
    }
}
