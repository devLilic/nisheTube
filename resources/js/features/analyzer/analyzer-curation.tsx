import { Form, useForm } from '@inertiajs/react';
import { BookmarkCheck, Tags } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { WorkspaceHandoff } from '@/features/integration/workspace-handoff';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type {
    AnalyzerCuration,
    AnalyzerRun,
    LibraryContext,
    LibraryTargetType,
} from '@/types';

type Subject = {
    type: 'video' | 'channel';
    reference: string;
    label: string;
    curation: AnalyzerCuration;
};

function SubjectCuration({
    run,
    library,
    subject,
}: {
    run: AnalyzerRun;
    library: LibraryContext;
    subject: Subject;
}) {
    const [confirmingRuledOut, setConfirmingRuledOut] = useState(false);
    const form = useForm({
        subject_type: subject.type,
        research_status: subject.curation.research_status,
        note: subject.curation.note ?? '',
    });
    const favorite = library.favorites.find(
        (item) =>
            item.target_type === subject.type &&
            item.target_reference === subject.reference,
    );
    const save = () =>
        form.patch(`/analyzer/runs/${run.public_id}/curation`, {
            preserveScroll: true,
            onSuccess: () => setConfirmingRuledOut(false),
        });

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <Badge variant="outline" className="capitalize">
                        {subject.type}
                    </Badge>
                    <p className="mt-2 font-medium break-words">
                        {subject.label}
                    </p>
                </div>
                <FavoriteToggle
                    library={library}
                    targetType={subject.type as LibraryTargetType}
                    targetReference={subject.reference}
                    label={subject.label}
                />
            </div>

            <div className="mt-4 grid gap-4">
                <label className="grid gap-2 text-sm font-medium">
                    Research status
                    <select
                        value={form.data.research_status}
                        onChange={(event) =>
                            form.setData(
                                'research_status',
                                event.target
                                    .value as AnalyzerCuration['research_status'],
                            )
                        }
                        className="h-10 rounded-md border bg-background px-3 font-normal focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="unreviewed">Unreviewed</option>
                        <option value="researching">Researching</option>
                        <option value="promising">Promising</option>
                        <option value="ruled_out">Ruled out</option>
                    </select>
                </label>
                <label className="grid gap-2 text-sm font-medium">
                    Private research note
                    <textarea
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        rows={4}
                        maxLength={10000}
                        className="min-h-24 resize-y rounded-md border bg-background px-3 py-2 font-normal focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        placeholder="Record why this subject matters, what to verify, or why it was ruled out."
                    />
                </label>
                {form.errors.note && (
                    <p className="text-sm text-destructive">
                        {form.errors.note}
                    </p>
                )}
                <Button
                    type="button"
                    className="w-fit"
                    disabled={form.processing || !form.isDirty}
                    onClick={() =>
                        form.data.research_status === 'ruled_out'
                            ? setConfirmingRuledOut(true)
                            : save()
                    }
                >
                    {form.processing ? <Spinner /> : <BookmarkCheck />}
                    Save curation
                </Button>
            </div>

            <div className="mt-5 border-t pt-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Tags className="size-4" /> Tags
                </div>
                {!favorite ? (
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                        Add this subject to Favorites before attaching library
                        tags.
                    </p>
                ) : library.tags.length === 0 ? (
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                        Create tags in Favorites, then attach them here.
                    </p>
                ) : (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {library.tags.map((tag) => {
                            const attached = favorite.tags.some(
                                (item) => item.public_id === tag.public_id,
                            );

                            return (
                                <Form
                                    key={tag.public_id}
                                    action={`/library/tags/${tag.public_id}/targets`}
                                    method={attached ? 'delete' : 'post'}
                                    disableWhileProcessing
                                >
                                    <input
                                        type="hidden"
                                        name="target_type"
                                        value={subject.type}
                                    />
                                    <input
                                        type="hidden"
                                        name="target_reference"
                                        value={subject.reference}
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant={
                                            attached ? 'secondary' : 'outline'
                                        }
                                    >
                                        {attached ? 'Remove ' : 'Add '}
                                        {tag.name}
                                    </Button>
                                </Form>
                            );
                        })}
                    </div>
                )}
            </div>

            <Dialog
                open={confirmingRuledOut}
                onOpenChange={setConfirmingRuledOut}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Mark this {subject.type} as ruled out?
                        </DialogTitle>
                        <DialogDescription>
                            This changes only your private research status for “
                            {subject.label}”. It does not delete observations or
                            remove a favorite.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button onClick={save} disabled={form.processing}>
                            {form.processing && <Spinner />} Mark ruled out
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export function AnalyzerCurationPanel({
    run,
    library,
    workspaces,
    compact = false,
    hideHandoffs = false,
}: {
    run: AnalyzerRun;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    compact?: boolean;
    hideHandoffs?: boolean;
}) {
    if (!run.channel || !run.curation) {
        return null;
    }

    const subjects: Subject[] = [
        ...(run.video && run.curation.video
            ? [
                  {
                      type: 'video' as const,
                      reference: run.video.provider_video_id,
                      label: run.video.title,
                      curation: run.curation.video,
                  },
              ]
            : []),
        {
            type: 'channel',
            reference: run.channel.provider_channel_id,
            label: run.channel.title,
            curation: run.curation.channel,
        },
    ];

    if (compact) {
        const subject = subjects[0];

        return (
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">
                        Keep this evidence moving
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Shortlist and Workspace actions reference this immutable
                        attempt.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <FavoriteToggle
                        library={library}
                        targetType={subject.type as LibraryTargetType}
                        targetReference={subject.reference}
                        label={subject.label}
                        context="shortlist"
                    />
                    <WorkspaceHandoff
                        workspaces={workspaces}
                        targetType="analyzer_run"
                        targetReference={run.public_id}
                    />
                </div>
            </div>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Research curation</CardTitle>
                <CardDescription>
                    Notes and status are private to your account. Favorites and
                    tags reuse the existing Library aggregate.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {subjects.map((subject) => (
                    <SubjectCuration
                        key={subject.type}
                        run={run}
                        library={library}
                        subject={subject}
                    />
                ))}
                {!hideHandoffs && (
                    <div className="grid gap-3 border-t pt-5 md:grid-cols-2">
                        {run.handoffs?.watchlist.watchlist_public_id ? (
                            <Button variant="outline" asChild>
                                <a href="/watchlist">Open watched item</a>
                            </Button>
                        ) : (
                            <Form
                                action="/watchlist"
                                method="post"
                                disableWhileProcessing
                            >
                                <input
                                    type="hidden"
                                    name="target_type"
                                    value={run.handoffs?.watchlist.target_kind}
                                />
                                <input
                                    type="hidden"
                                    name="target_reference"
                                    value={
                                        run.handoffs?.watchlist.target_reference
                                    }
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="w-full"
                                >
                                    Add to Watchlist
                                </Button>
                            </Form>
                        )}
                        <WorkspaceHandoff
                            workspaces={workspaces}
                            targetType="analyzer_run"
                            targetReference={run.public_id}
                        />
                    </div>
                )}
                {!hideHandoffs && (
                    <p className="text-xs leading-5 text-muted-foreground">
                        Watchlist is an explicit repeated-observation opt-in and
                        remains independent from Favorites. Workspace links
                        reuse this immutable attempt and copy no metric payload.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
