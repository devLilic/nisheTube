<?php

namespace App\Domain\Comments\ReadModels;

use App\Models\SavedCommentIdea;
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
            ->with('video')
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
