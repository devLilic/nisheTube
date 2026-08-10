import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    HeartOff,
    Lightbulb,
    LoaderCircle,
} from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import type { PaginationMeta } from '@/components/pagination-controls';
import { PaginationControls } from '@/components/pagination-controls';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/formatters';
import type { Auth, SavedCommentIdea } from '@/types';

type Props = {
    auth: Auth;
    items: SavedCommentIdea[];
    pagination: PaginationMeta;
};

export default function IdeasIndex({ auth, items, pagination }: Props) {
    return (
        <>
            <Head title="Ideas" />
            <PageContainer>
                <PageHeader
                    title="Saved comment ideas"
                    description="Interesting audience messages saved from Analyzer, with their source videos kept one click away."
                />

                {items.length === 0 ? (
                    <StatePanel
                        icon={Lightbulb}
                        title="No saved ideas yet"
                        description="Open a completed video analysis, collect public comments, then use the heart control beside any useful message."
                        action={
                            <Button asChild>
                                <Link href="/analyzer">Open Analyzer</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {items.map((idea) => (
                            <IdeaCard
                                key={idea.public_id}
                                idea={idea}
                                timezone={auth.user.timezone}
                            />
                        ))}
                    </div>
                )}

                <PaginationControls
                    pagination={pagination}
                    ariaLabel="Saved ideas pagination"
                    onPageChange={(page) =>
                        router.get(
                            '/ideas',
                            { page },
                            {
                                preserveState: true,
                                preserveScroll: true,
                                replace: true,
                            },
                        )
                    }
                />
            </PageContainer>
        </>
    );
}

function IdeaCard({
    idea,
    timezone,
}: {
    idea: SavedCommentIdea;
    timezone: string;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="gap-3 sm:flex-row sm:items-start">
                {idea.video.thumbnail_url ? (
                    <img
                        src={idea.video.thumbnail_url}
                        alt=""
                        className="aspect-video w-full rounded-md object-cover sm:w-32"
                    />
                ) : (
                    <div className="grid aspect-video w-full place-items-center rounded-md bg-muted text-muted-foreground sm:w-32">
                        <Lightbulb aria-hidden="true" />
                        <span className="sr-only">
                            Video thumbnail unavailable
                        </span>
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <Badge variant="outline">Saved comment</Badge>
                    <CardTitle className="mt-2 text-base break-words">
                        {idea.video.title}
                    </CardTitle>
                    {idea.saved_at && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Saved {formatDate(idea.saved_at, timezone)}
                        </p>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <blockquote className="border-l-2 pl-4 text-sm break-words whitespace-pre-wrap">
                    {idea.text}
                </blockquote>

                {!idea.source_comment_available && (
                    <Alert>
                        <AlertCircle />
                        <AlertTitle>Original collection cleaned up</AlertTitle>
                        <AlertDescription>
                            This saved copy and its video link remain available,
                            but the retained raw comment collection has expired.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" variant="outline">
                        <a
                            href={idea.video.youtube_url}
                            target="_blank"
                            rel="noreferrer noopener"
                        >
                            Open source video <ArrowUpRight />
                        </a>
                    </Button>
                    <Form
                        action={`/ideas/${idea.public_id}`}
                        method="delete"
                        disableWhileProcessing
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-1">
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="ghost"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <HeartOff />
                                    )}
                                    {processing ? 'Removing...' : 'Remove like'}
                                </Button>
                                {Object.keys(errors).length > 0 && (
                                    <p
                                        className="text-xs text-destructive"
                                        role="alert"
                                    >
                                        The idea could not be removed. Try
                                        again.
                                    </p>
                                )}
                            </div>
                        )}
                    </Form>
                </div>
            </CardContent>
        </Card>
    );
}

IdeasIndex.layout = {
    breadcrumbs: [{ title: 'Ideas', href: '/ideas' }],
};
