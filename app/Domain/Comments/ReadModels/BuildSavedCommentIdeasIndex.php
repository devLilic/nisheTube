<?php

namespace App\Domain\Comments\ReadModels;

use App\Models\NicheCandidate;
use App\Models\SavedCommentIdea;
use App\Models\TopicWorkspace;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BuildSavedCommentIdeasIndex
{
    private const PER_PAGE = 20;

    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $page = SavedCommentIdea::query()
            ->where('user_id', $user->id)
            ->with(['video', 'workspace', 'candidate.discoveryRun'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'items' => $page->getCollection()
                ->map(fn (SavedCommentIdea $idea): array => $this->item($idea))
                ->values()
                ->all(),
            'pagination' => $this->pagination($page),
            'context_options' => [
                'workspaces' => TopicWorkspace::query()->where('user_id', $user->id)->whereNull('archived_at')->orderBy('name')->limit(100)->get()
                    ->map(fn (TopicWorkspace $workspace): array => ['public_id' => $workspace->public_id, 'name' => $workspace->name, 'market_key' => $workspace->market_key])->values()->all(),
                'candidates' => NicheCandidate::query()->with('discoveryRun')->whereHas('discoveryRun', fn ($runs) => $runs->where('user_id', $user->id))
                    ->latest('id')->limit(100)->get()
                    ->map(fn (NicheCandidate $candidate): array => ['public_id' => $candidate->public_id, 'phrase' => $candidate->phrase, 'market_key' => $candidate->discoveryRun->market_key])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function item(SavedCommentIdea $idea): array
    {
        return [
            'public_id' => $idea->public_id,
            'text' => $idea->comment_text,
            'provider_comment_id' => $idea->provider_comment_id,
            'comment_published_at' => $idea->source_published_at?->toIso8601String(),
            'saved_at' => $idea->created_at?->toIso8601String(),
            'source_comment_available' => $idea->public_comment_id !== null,
            'context' => [
                'decision_status' => $idea->decision_status,
                'format' => $idea->format,
                'audience' => $idea->audience,
                'decision_note' => $idea->decision_note,
                'workspace' => $idea->workspace === null ? null : ['public_id' => $idea->workspace->public_id, 'name' => $idea->workspace->name, 'market_key' => $idea->workspace->market_key],
                'candidate' => $idea->candidate === null ? null : ['public_id' => $idea->candidate->public_id, 'phrase' => $idea->candidate->phrase, 'market_key' => $idea->candidate->discoveryRun->market_key],
                'workspace_available' => $idea->topic_workspace_id === null || $idea->workspace !== null,
                'candidate_available' => $idea->niche_candidate_id === null || $idea->candidate !== null,
            ],
            'video' => [
                'provider_video_id' => $idea->video->provider_video_id,
                'title' => $idea->video->title,
                'thumbnail_url' => $idea->video->thumbnail_url,
                'youtube_url' => 'https://www.youtube.com/watch?v='.$idea->video->provider_video_id,
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, SavedCommentIdea>  $page
     * @return array<string, int|null>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
        ];
    }
}
