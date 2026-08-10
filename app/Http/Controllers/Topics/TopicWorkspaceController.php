<?php

namespace App\Http\Controllers\Topics;

use App\Domain\Discovery\Actions\StartDiscoveryRun;
use App\Domain\Research\Actions\StartResearchRun;
use App\Domain\Research\Enums\PublishedWindow;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Domain\Topics\Actions\AddTopicEvidence;
use App\Domain\Topics\Actions\CreateTopicWorkspace;
use App\Domain\Topics\Actions\UpdateTopicWorkspace;
use App\Domain\Topics\Enums\TopicEvidenceRole;
use App\Domain\Topics\Enums\TopicEvidenceType;
use App\Domain\Topics\ReadModels\BuildTopicWorkspaceDetail;
use App\Domain\Topics\ReadModels\BuildTopicWorkspaceIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Topics\LaunchTopicDiscoveryRequest;
use App\Http\Requests\Topics\LaunchTopicSearchRequest;
use App\Http\Requests\Topics\StoreTopicEvidenceRequest;
use App\Http\Requests\Topics\StoreTopicWorkspaceRequest;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\TopicWorkspaceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TopicWorkspaceController extends Controller
{
    public function index(Request $request, BuildTopicWorkspaceIndex $view): Response
    {
        Gate::authorize('viewAny', TopicWorkspace::class);
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => in_array($request->string('status')->toString(), ['all', 'active', 'archived'], true) ? $request->string('status')->toString() : 'active',
            'market' => $request->string('market')->toString() ?: 'all',
        ];

        return Inertia::render('topics/index', $view->handle($request->user(), $filters));
    }

    public function store(StoreTopicWorkspaceRequest $request, CreateTopicWorkspace $create): RedirectResponse
    {
        Gate::authorize('create', TopicWorkspace::class);
        $workspace = $create->handle(
            $request->user(),
            Market::query()->where('key', $request->validated('market_key'))->firstOrFail(),
            (string) $request->validated('name'), $request->description(), $this->project($request),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Topic Workspace created.')]);

        return to_route('topics.show', $workspace);
    }

    public function show(Request $request, TopicWorkspace $topicWorkspace, BuildTopicWorkspaceDetail $view): Response
    {
        Gate::authorize('view', $topicWorkspace);
        $filters = [
            'role' => in_array($request->string('role')->toString(), ['all', ...array_column(TopicEvidenceRole::cases(), 'value')], true) ? $request->string('role')->toString() : 'all',
            'type' => in_array($request->string('type')->toString(), ['all', ...array_column(TopicEvidenceType::cases(), 'value')], true) ? $request->string('type')->toString() : 'all',
        ];

        return Inertia::render('topics/show', $view->handle($request->user(), $topicWorkspace->load('project'), $filters));
    }

    public function update(StoreTopicWorkspaceRequest $request, TopicWorkspace $topicWorkspace, UpdateTopicWorkspace $update): RedirectResponse
    {
        Gate::authorize('update', $topicWorkspace);
        abort_unless($request->validated('market_key') === $topicWorkspace->market_key, 422, 'A workspace market cannot be changed after creation.');
        $update->handle($request->user(), $topicWorkspace, (string) $request->validated('name'), $request->description(), $this->project($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Topic Workspace updated.')]);

        return back();
    }

    public function archive(Request $request, TopicWorkspace $topicWorkspace): RedirectResponse
    {
        Gate::authorize('archive', $topicWorkspace);
        $topicWorkspace->update(['archived_at' => now()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Topic Workspace archived. Its evidence remains available read-only.')]);

        return back();
    }

    public function restore(Request $request, TopicWorkspace $topicWorkspace): RedirectResponse
    {
        Gate::authorize('archive', $topicWorkspace);
        $topicWorkspace->update(['archived_at' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Topic Workspace restored.')]);

        return back();
    }

    public function addEvidence(StoreTopicEvidenceRequest $request, TopicWorkspace $topicWorkspace, AddTopicEvidence $add): RedirectResponse
    {
        Gate::authorize('addEvidence', $topicWorkspace);
        $item = $add->handle($request->user(), $topicWorkspace, $request->type(), (string) $request->validated('target_reference'), $request->role(), $request->note());
        Inertia::flash('toast', ['type' => 'success', 'message' => $item->wasRecentlyCreated ? __('Evidence linked without copying metrics.') : __('That evidence is already linked.')]);

        return back();
    }

    public function removeEvidence(Request $request, TopicWorkspace $topicWorkspace, TopicWorkspaceItem $topicWorkspaceItem): RedirectResponse
    {
        Gate::authorize('update', $topicWorkspace);
        abort_unless($topicWorkspaceItem->topic_workspace_id === $topicWorkspace->id, 404);
        abort_if($topicWorkspace->archived_at !== null, 403);
        $topicWorkspaceItem->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Evidence link removed. The source record was not deleted.')]);

        return back();
    }

    public function launchSearch(LaunchTopicSearchRequest $request, TopicWorkspace $topicWorkspace, StartResearchRun $start): RedirectResponse
    {
        Gate::authorize('launch', $topicWorkspace);
        $market = Market::query()->where('key', $topicWorkspace->market_key)->firstOrFail();
        $run = $start->handle($request->user(), $market, (string) $request->validated('query_text'), $request->user()->default_result_depth, SearchOrder::Relevance, PublishedWindow::Any, null, null, VideoDurationFilter::Any, null);
        $topicWorkspace->items()->firstOrCreate(['target_type' => 'research_run', 'target_id' => $run->id], ['evidence_role' => 'evidence', 'note' => 'Launched from this Topic Workspace.']);
        $topicWorkspace->launches()->create(['user_id' => $request->user()->id, 'launch_type' => 'search', 'research_run_id' => $run->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace Search queued and linked.')]);

        return to_route('research.runs.show', $run);
    }

    public function launchDiscovery(LaunchTopicDiscoveryRequest $request, TopicWorkspace $topicWorkspace, StartDiscoveryRun $start): RedirectResponse
    {
        Gate::authorize('launch', $topicWorkspace);
        $source = ResearchRun::query()->where('user_id', $request->user()->id)->where('public_id', $request->validated('research_run'))->where('market_key', $topicWorkspace->market_key)->where('status', 'completed')->whereHas('videoSnapshots')->firstOrFail();
        abort_unless($topicWorkspace->items()->where('target_type', 'research_run')->where('target_id', $source->id)->exists(), 404);
        $run = $start->handle($request->user(), Market::query()->where('key', $topicWorkspace->market_key)->firstOrFail(), [['query' => $topicWorkspace->name, 'research_run' => $source]], 25, 20);
        $topicWorkspace->launches()->create(['user_id' => $request->user()->id, 'launch_type' => 'discover', 'discovery_run_id' => $run->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Related discovery queued and linked to this workspace.')]);

        return to_route('discovery.runs.show', $run);
    }

    private function project(StoreTopicWorkspaceRequest $request): ?ResearchProject
    {
        $reference = $request->validated('project');

        return is_string($reference) && $reference !== '' ? ResearchProject::query()->where('user_id', $request->user()->id)->whereNull('archived_at')->where('public_id', $reference)->firstOrFail() : null;
    }
}
