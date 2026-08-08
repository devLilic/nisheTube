<?php

namespace App\Http\Controllers\Library;

use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Library\Actions\DeleteTag;
use App\Domain\Library\Actions\DetachTag;
use App\Domain\Library\Actions\UpdateTag;
use App\Domain\Library\Services\ResolveLibraryTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\TagRequest;
use App\Http\Requests\Library\TagTargetRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TagController extends Controller
{
    public function store(TagRequest $request, CreateTag $createTag): RedirectResponse
    {
        Gate::authorize('create', Tag::class);
        $createTag->handle(
            $request->user(),
            (string) $request->validated('name'),
            $this->optionalString($request->validated('color')),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return back();
    }

    public function update(TagRequest $request, Tag $tag, UpdateTag $updateTag): RedirectResponse
    {
        Gate::authorize('update', $tag);
        $updateTag->handle(
            $request->user(),
            $tag,
            (string) $request->validated('name'),
            $this->optionalString($request->validated('color')),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag updated.')]);

        return back();
    }

    public function attach(
        TagTargetRequest $request,
        Tag $tag,
        ResolveLibraryTarget $resolveTarget,
        AttachTag $attachTag,
    ): RedirectResponse {
        Gate::authorize('update', $tag);
        $target = $resolveTarget->handle(
            $request->user(),
            $request->targetType(),
            (string) $request->validated('target_reference'),
        );
        $attachTag->handle($request->user(), $tag, $target);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag attached.')]);

        return back();
    }

    public function detach(
        TagTargetRequest $request,
        Tag $tag,
        ResolveLibraryTarget $resolveTarget,
        DetachTag $detachTag,
    ): RedirectResponse {
        Gate::authorize('update', $tag);
        $target = $resolveTarget->handle(
            $request->user(),
            $request->targetType(),
            (string) $request->validated('target_reference'),
        );
        $detachTag->handle($request->user(), $tag, $target);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag removed.')]);

        return back();
    }

    public function destroy(Request $request, Tag $tag, DeleteTag $deleteTag): RedirectResponse
    {
        Gate::authorize('delete', $tag);
        $deleteTag->handle($request->user(), $tag);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
