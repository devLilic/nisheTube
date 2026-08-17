<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Services\ResearchContextResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResearchContextUpdateRequest;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use Illuminate\Http\RedirectResponse;

class ResearchContextController extends Controller
{
    public function update(ResearchContextUpdateRequest $request, ResearchContextResolver $resolver): RedirectResponse
    {
        $marketKey = $request->nullableString('market_key');
        $projectReference = $request->nullableString('project');
        $workspaceReference = $request->nullableString('workspace');
        $workspace = $workspaceReference === null ? null : TopicWorkspace::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->where('public_id', $workspaceReference)
            ->with('project')
            ->firstOrFail();

        if ($request->changed() === 'workspace' && $workspace !== null) {
            $marketKey = $workspace->market_key;
            $projectReference = $workspace->research_project_id === null
                ? $projectReference
                : $workspace->project->public_id;
        } elseif ($workspace !== null && $workspace->market_key !== $marketKey) {
            $workspaceReference = null;
        }

        if ($workspace !== null && $request->changed() === 'project') {
            $project = $projectReference === null ? null : ResearchProject::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('archived_at')
                ->where('public_id', $projectReference)
                ->first();

            if ($workspace->research_project_id !== null && $workspace->research_project_id !== $project?->id) {
                $workspaceReference = null;
            }
        }

        $resolved = $resolver->resolve($request->user(), [
            'user_id' => $request->user()->id,
            'market_key' => $marketKey,
            'project' => $projectReference,
            'workspace' => $workspaceReference,
        ]);
        $request->session()->put(ResearchContextResolver::SESSION_KEY, $resolved['stored']);

        return back();
    }
}
