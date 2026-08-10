import { Form, router } from '@inertiajs/react';
import {
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Heart,
    LoaderCircle,
    MessageSquareText,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/formatters';
import type { AnalyzerComments, AnalyzerRun } from '@/types';

export function AnalyzerCommentsSection({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const comments = run.comments;

    if (!comments) {
        return null;
    }

    const collect = () =>
        router.post(`/analyzer/runs/${run.public_id}/comments`, {});
    const showPage = (page: number) =>
        router.get(
            `/analyzer/runs/${run.public_id}`,
            {
                comments_page: page,
                ...(run.origin.return_url
                    ? { return_to: run.origin.return_url }
                    : {}),
            },
            {
                only: ['run'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    const stateCopy: Record<string, { title: string; description: string }> = {
        comments_disabled: {
            title: 'Comments are disabled',
            description:
                'YouTube does not allow public comment collection for this video.',
        },
        empty: {
            title: 'No public comments returned',
            description:
                'The collection completed, but YouTube returned no top-level comment threads.',
        },
        unavailable: {
            title: 'Comments are unavailable',
            description:
                'The video or its public comments are no longer available through YouTube.',
        },
        quota_exhausted: {
            title: 'Comment quota unavailable',
            description:
                'The YouTube quota bucket is exhausted. Try again after the displayed quota reset.',
        },
        failed: {
            title: 'Comment collection failed',
            description:
                comments.error_message ??
                'YouTube could not complete this collection.',
        },
    };
    const state = stateCopy[comments.status];

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <MessageSquareText className="size-5" /> Comments
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Opt-in public top-level comment evidence. Author
                        identity and reply text are not stored.
                    </p>
                </div>
                {comments.can_collect && (
                    <Button onClick={collect}>
                        {comments.status === 'not_requested'
                            ? 'Collect public comments'
                            : 'Collect again'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {comments.status === 'not_requested' && (
                    <Alert>
                        <MessageSquareText />
                        <AlertTitle>Comment collection is off</AlertTitle>
                        <AlertDescription>
                            Loading this Analyzer result makes no comment API
                            request. Collection is explicit and uses additional
                            YouTube quota.
                        </AlertDescription>
                    </Alert>
                )}

                {comments.is_active && (
                    <Alert aria-busy="true">
                        <LoaderCircle className="animate-spin" />
                        <AlertTitle>Collecting public comments</AlertTitle>
                        <AlertDescription>
                            {comments.comments_collected ?? 0} top-level
                            comments stored across{' '}
                            {comments.pages_collected ?? 0} page(s).
                        </AlertDescription>
                    </Alert>
                )}

                {state && (
                    <Alert
                        variant={
                            comments.status === 'failed'
                                ? 'destructive'
                                : 'default'
                        }
                    >
                        <AlertCircle />
                        <AlertTitle>{state.title}</AlertTitle>
                        <AlertDescription>{state.description}</AlertDescription>
                    </Alert>
                )}

                {comments.status === 'partial' && (
                    <Alert>
                        <AlertCircle />
                        <AlertTitle>Partial comment sample</AlertTitle>
                        <AlertDescription>
                            {comments.error_message ??
                                'The configured limit was reached before every thread was retrieved.'}{' '}
                            Reply counts are YouTube-reported totals; reply text
                            was not collected, so reply completeness is not
                            implied.
                        </AlertDescription>
                    </Alert>
                )}

                {(comments.items?.length ?? 0) > 0 && (
                    <>
                        <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline">
                                {comments.comments_collected} top-level comments
                            </Badge>
                            <Badge variant="outline">
                                {comments.pages_collected} API page(s)
                            </Badge>
                            {comments.collected_at && (
                                <span>
                                    Collected{' '}
                                    {formatDate(
                                        comments.collected_at,
                                        timezone,
                                    )}
                                </span>
                            )}
                            {comments.retention_cutoff_at && (
                                <span>
                                    Eligible for cleanup{' '}
                                    {formatDate(
                                        comments.retention_cutoff_at,
                                        timezone,
                                    )}
                                </span>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Reply counts provide context only. Reply text and
                            comment-author identity are not collected.
                        </p>
                        <ol
                            id="stored-public-comments"
                            className="space-y-3"
                            aria-label="Stored public comments"
                        >
                            {comments.items.map((comment, index) => (
                                <CommentCard
                                    key={`${comment.published_at ?? 'unknown'}-${index}`}
                                    comment={comment}
                                    timezone={timezone}
                                />
                            ))}
                        </ol>
                        {comments.pagination.last_page > 1 && (
                            <nav
                                className="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
                                aria-label="Comment pages"
                            >
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    Showing {comments.pagination.from}–
                                    {comments.pagination.to} of{' '}
                                    {comments.pagination.total} comments · Page{' '}
                                    {comments.pagination.current_page} of{' '}
                                    {comments.pagination.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            comments.pagination.current_page <=
                                            1
                                        }
                                        onClick={() =>
                                            showPage(
                                                comments.pagination
                                                    .current_page - 1,
                                            )
                                        }
                                    >
                                        <ChevronLeft /> Previous
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            comments.pagination.current_page >=
                                            comments.pagination.last_page
                                        }
                                        onClick={() =>
                                            showPage(
                                                comments.pagination
                                                    .current_page + 1,
                                            )
                                        }
                                    >
                                        Next <ChevronRight />
                                    </Button>
                                </div>
                            </nav>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function CommentCard({
    comment,
    timezone,
}: {
    comment: AnalyzerComments['items'][number];
    timezone: string;
}) {
    return (
        <li className="rounded-lg border p-4">
            <p className="text-sm break-words whitespace-pre-wrap">
                {comment.text}
            </p>
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span>{comment.like_count ?? 'Unknown'} likes</span>
                    <span>{comment.reply_count} reported replies</span>
                    {comment.published_at && (
                        <span>
                            Published{' '}
                            {formatDate(comment.published_at, timezone)}
                        </span>
                    )}
                </div>
                <Form
                    action={
                        comment.is_saved && comment.saved_idea_public_id
                            ? `/ideas/${comment.saved_idea_public_id}`
                            : `/ideas/comments/${comment.id}`
                    }
                    method={comment.is_saved ? 'delete' : 'post'}
                    disableWhileProcessing
                >
                    {({ processing, errors }) => (
                        <div className="grid justify-items-end gap-1">
                            <Button
                                type="submit"
                                size="sm"
                                variant={
                                    comment.is_saved ? 'secondary' : 'outline'
                                }
                                aria-label={
                                    comment.is_saved
                                        ? 'Remove comment from Ideas'
                                        : 'Save comment to Ideas'
                                }
                                title={
                                    comment.is_saved
                                        ? 'Remove from Ideas'
                                        : 'Save as an idea'
                                }
                                disabled={processing}
                            >
                                <Heart
                                    className={
                                        comment.is_saved
                                            ? 'fill-current'
                                            : undefined
                                    }
                                />
                                {processing
                                    ? 'Updating...'
                                    : comment.is_saved
                                      ? 'Saved idea'
                                      : 'Save idea'}
                            </Button>
                            {Object.keys(errors).length > 0 && (
                                <p
                                    className="text-xs text-destructive"
                                    role="alert"
                                >
                                    The idea could not be updated. Try again.
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            </div>
        </li>
    );
}
