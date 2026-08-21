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
import type {
    Auth,
    IdeaDecisionContextOptions,
    SavedCommentIdea,
} from '@/types';

type Props = {
    auth: Auth;
    items: SavedCommentIdea[];
    pagination: PaginationMeta;
    context_options: IdeaDecisionContextOptions;
};

export default function IdeasIndex({
    auth,
    items,
    pagination,
    context_options,
}: Props) {
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
                                contextOptions={context_options}
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
    contextOptions,
}: {
    idea: SavedCommentIdea;
    timezone: string;
    contextOptions: IdeaDecisionContextOptions;
}) {
    const hasContext =
        idea.context.decision_status !== 'new' ||
        idea.context.format !== null ||
        idea.context.audience !== null ||
        idea.context.decision_note !== null ||
        idea.context.workspace !== null ||
        idea.context.candidate !== null;

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

                {(!idea.context.workspace_available ||
                    !idea.context.candidate_available) && (
                    <Alert>
                        <AlertCircle />
                        <AlertTitle>
                            Linked decision context unavailable
                        </AlertTitle>
                        <AlertDescription>
                            The saved comment and source video remain intact.
                            Choose a currently available context when updating
                            this idea.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="rounded-lg border p-3 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium">Decision context</p>
                        <Badge variant="outline">
                            {idea.context.decision_status.replace('_', ' ')}
                        </Badge>
                        {idea.context.workspace && (
                            <Badge variant="secondary">
                                {idea.context.workspace.name}
                            </Badge>
                        )}
                        {idea.context.candidate && (
                            <Badge variant="secondary">
                                {idea.context.candidate.phrase}
                            </Badge>
                        )}
                    </div>
                    {hasContext ? (
                        <div className="mt-2 space-y-1 text-muted-foreground">
                            {idea.context.format && (
                                <p>Format: {idea.context.format}</p>
                            )}
                            {idea.context.audience && (
                                <p>Audience: {idea.context.audience}</p>
                            )}
                            {idea.context.decision_note && (
                                <p className="whitespace-pre-wrap">
                                    {idea.context.decision_note}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="mt-1 text-muted-foreground">
                            No decision context yet. The saved comment source
                            remains unchanged.
                        </p>
                    )}
                </div>

                <details className="rounded-lg border p-3">
                    <summary className="cursor-pointer text-sm font-medium">
                        Edit decision context
                    </summary>
                    <Form
                        action={`/ideas/${idea.public_id}`}
                        method="patch"
                        disableWhileProcessing
                        className="mt-4 grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <label className="grid gap-1 text-sm font-medium">
                                        Status
                                        <select
                                            name="decision_status"
                                            defaultValue={
                                                idea.context.decision_status
                                            }
                                            className="h-10 rounded-md border bg-background px-3 capitalize"
                                        >
                                            {[
                                                'new',
                                                'reviewing',
                                                'selected',
                                                'ruled_out',
                                            ].map((status) => (
                                                <option
                                                    key={status}
                                                    value={status}
                                                >
                                                    {status.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-sm font-medium">
                                        Topic Workspace
                                        <select
                                            name="workspace"
                                            defaultValue={
                                                idea.context.workspace
                                                    ?.public_id ?? ''
                                            }
                                            className="h-10 rounded-md border bg-background px-3"
                                        >
                                            <option value="">
                                                No workspace
                                            </option>
                                            {contextOptions.workspaces.map(
                                                (workspace) => (
                                                    <option
                                                        key={
                                                            workspace.public_id
                                                        }
                                                        value={
                                                            workspace.public_id
                                                        }
                                                    >
                                                        {workspace.name} ·{' '}
                                                        {workspace.market_key}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                </div>
                                <label className="grid gap-1 text-sm font-medium">
                                    Discovery candidate
                                    <select
                                        name="candidate"
                                        defaultValue={
                                            idea.context.candidate?.public_id ??
                                            ''
                                        }
                                        className="h-10 rounded-md border bg-background px-3"
                                    >
                                        <option value="">No candidate</option>
                                        {contextOptions.candidates.map(
                                            (candidate) => (
                                                <option
                                                    key={candidate.public_id}
                                                    value={candidate.public_id}
                                                >
                                                    {candidate.phrase} ·{' '}
                                                    {candidate.market_key}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <label className="grid gap-1 text-sm font-medium">
                                        Format
                                        <Input
                                            name="format"
                                            defaultValue={
                                                idea.context.format ?? ''
                                            }
                                            maxLength={120}
                                            placeholder="For example, tutorial"
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm font-medium">
                                        Audience
                                        <Input
                                            name="audience"
                                            defaultValue={
                                                idea.context.audience ?? ''
                                            }
                                            maxLength={240}
                                            placeholder="For example, new creators"
                                        />
                                    </label>
                                </div>
                                <label className="grid gap-1 text-sm font-medium">
                                    Decision note
                                    <textarea
                                        name="decision_note"
                                        defaultValue={
                                            idea.context.decision_note ?? ''
                                        }
                                        rows={3}
                                        maxLength={4000}
                                        className="rounded-md border bg-background p-2 font-normal"
                                    />
                                </label>
                                {Object.keys(errors).length > 0 && (
                                    <p
                                        className="text-sm text-destructive"
                                        role="alert"
                                    >
                                        {errors.workspace ||
                                            errors.candidate ||
                                            errors.decision_status ||
                                            'The decision context could not be updated.'}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : null}
                                    {processing
                                        ? 'Saving...'
                                        : 'Save decision context'}
                                </Button>
                            </>
                        )}
                    </Form>
                </details>

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
