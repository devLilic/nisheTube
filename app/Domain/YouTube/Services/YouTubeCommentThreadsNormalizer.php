<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\CommentThreadsPage;
use App\Domain\YouTube\Data\PublicComment;
use DateTimeImmutable;
use Throwable;

final class YouTubeCommentThreadsNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): CommentThreadsPage
    {
        $comments = [];
        $warnings = [];
        foreach (is_array($payload['items'] ?? null) ? $payload['items'] : [] as $item) {
            $top = is_array($item) ? ($item['snippet']['topLevelComment'] ?? null) : null;
            $snippet = is_array($top['snippet'] ?? null) ? $top['snippet'] : null;
            $id = $top['id'] ?? null;
            $text = is_array($snippet) ? ($snippet['textOriginal'] ?? $snippet['textDisplay'] ?? null) : null;
            if (! is_string($id) || trim($id) === '' || ! is_string($text) || trim($text) === '') {
                $warnings[] = 'A comment thread was omitted because its top-level text was incomplete.';

                continue;
            }

            $comments[] = new PublicComment(
                trim($id),
                mb_substr(trim($text), 0, 10000),
                $this->nullableCount($snippet['likeCount'] ?? null),
                $this->nullableCount(is_array($item) ? ($item['snippet']['totalReplyCount'] ?? null) : null) ?? 0,
                $this->date($snippet['publishedAt'] ?? null),
                $this->date($snippet['updatedAt'] ?? null),
            );
        }

        $next = $payload['nextPageToken'] ?? null;
        $total = is_array($payload['pageInfo'] ?? null) ? ($payload['pageInfo']['totalResults'] ?? null) : null;

        return new CommentThreadsPage(
            $comments,
            is_string($next) && $next !== '' ? $next : null,
            $this->nullableCount($total),
            array_values(array_unique($warnings)),
        );
    }

    private function nullableCount(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
