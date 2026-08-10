import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Binoculars,
    Pause,
    Play,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import type { PaginationMeta } from '@/components/pagination-controls';
import { PaginationControls } from '@/components/pagination-controls';
import { PartialDataBanner } from '@/components/partial-data-banner';
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
import { Input } from '@/components/ui/input';
import type { Auth, WatchlistFilters, WatchlistItem } from '@/types';

type Props = {
    auth: Auth;
    filters: WatchlistFilters;
    items: WatchlistItem[];
    pagination: PaginationMeta;
    projects: { public_id: string; name: string; color: string | null }[];
    tags: { public_id: string; name: string; color: string | null }[];
    workspaces: { public_id: string; name: string; market_key: string }[];
    counts: { all: number; active: number; video: number; channel: number };
    workspace_available: boolean;
};

export default function WatchlistIndex(props: Props) {
    const [draft, setDraft] = useState(props.filters);
    const hasActive = props.items.some((item) => item.refresh?.is_active);

    useEffect(() => {
        if (!hasActive) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['items', 'counts'] }),
            3000,
        );

        return () => window.clearInterval(timer);
    }, [hasActive]);

    const apply = () =>
        router.get('/watchlist', draft, { preserveState: true, replace: true });

    return (
        <>
            <Head title="Watchlist" />
            <PageContainer>
                <PageHeader
                    title="Watchlist"
                    description="Explicitly observe selected videos and channels over time. Favorites remain bookmarks and never trigger refreshes."
                />
                <AnalyticsGlossary page="watchlist" />
                <div className="grid gap-3 sm:grid-cols-4">
                    {Object.entries(props.counts).map(([label, value]) => (
                        <Card key={label}>
                            <CardContent className="pt-5">
                                <p className="text-xs text-muted-foreground uppercase">
                                    {label}
                                </p>
                                <p className="text-2xl font-semibold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Filter watched subjects</CardTitle>
                        <CardDescription>
                            Filtering uses stored evidence and consumes no
                            YouTube quota.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-5">
                        <Input
                            value={draft.search}
                            onChange={(e) =>
                                setDraft({ ...draft, search: e.target.value })
                            }
                            placeholder="Search title or note"
                        />
                        <Select
                            value={draft.type}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    type: value as WatchlistFilters['type'],
                                })
                            }
                            options={['all', 'video', 'channel']}
                        />
                        <Select
                            value={draft.status}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    status: value as WatchlistFilters['status'],
                                })
                            }
                            options={[
                                'all',
                                'monitoring',
                                'attention',
                                'promising',
                                'ruled_out',
                            ]}
                        />
                        <Select
                            value={draft.activity}
                            onChange={(value) =>
                                setDraft({
                                    ...draft,
                                    activity:
                                        value as WatchlistFilters['activity'],
                                })
                            }
                            options={['all', 'active', 'paused']}
                        />
                        <Button onClick={apply}>Apply filters</Button>
                    </CardContent>
                </Card>
                {props.items.length === 0 ? (
                    <StatePanel
                        icon={Binoculars}
                        title="Nothing watched here yet"
                        description="Add a stored video or channel from Analyzer or Explore. Adding is quota-free; refresh is always a separate explicit action."
                        action={
                            <Button asChild>
                                <Link href="/explore">
                                    Explore stored evidence
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {props.items.map((item) => (
                            <WatchCard
                                key={item.public_id}
                                item={item}
                                projects={props.projects}
                                tags={props.tags}
                                workspaces={props.workspaces}
                            />
                        ))}
                    </div>
                )}
                <PaginationControls
                    pagination={props.pagination}
                    onPageChange={(page) =>
                        router.get(
                            '/watchlist',
                            { ...props.filters, page },
                            { preserveState: true },
                        )
                    }
                />
            </PageContainer>
        </>
    );
}

function WatchCard({
    item,
    projects,
    tags,
    workspaces,
}: {
    item: WatchlistItem;
    projects: Props['projects'];
    tags: Props['tags'];
    workspaces: Props['workspaces'];
}) {
    const [confirmRemove, setConfirmRemove] = useState(false);
    const form = useForm({
        status: item.status,
        is_active: item.is_active,
        project: item.project?.public_id ?? '',
        workspace: item.workspace?.public_id ?? '',
        note: item.note ?? '',
    });
    const deltas = useMemo(
        () =>
            item.refresh?.deltas
                ? Object.entries(
                      item.target_type === 'video'
                          ? item.refresh.deltas.video
                          : item.refresh.deltas.channel,
                  ).filter(([, value]) => value !== null)
                : [],
        [item],
    );
    const save = () =>
        form.patch(`/watchlist/${item.public_id}`, { preserveScroll: true });

    return (
        <Card className="overflow-hidden">
            <CardHeader>
                <div className="flex gap-3">
                    {item.thumbnail_url ? (
                        <img
                            src={item.thumbnail_url}
                            alt=""
                            className="size-16 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="grid size-16 place-items-center rounded-lg bg-muted">
                            <Binoculars />
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap gap-2">
                            <Badge>{item.target_type}</Badge>
                            <Badge
                                variant={
                                    item.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {item.is_active ? 'Active' : 'Paused'}
                            </Badge>
                            {item.favorite && (
                                <Badge variant="outline">Favorite too</Badge>
                            )}
                        </div>
                        <CardTitle className="mt-2 text-lg break-words">
                            {item.label}
                        </CardTitle>
                        <CardDescription>
                            {item.last_observed_at
                                ? `Observed ${new Date(item.last_observed_at).toLocaleString()}`
                                : 'No Watchlist observation yet'}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {item.refresh?.is_active && (
                    <div className="rounded-lg border p-3">
                        <div className="flex justify-between text-sm">
                            <span>Refreshing through Analyzer</span>
                            <span>{item.refresh.progress_percent}%</span>
                        </div>
                        <div
                            className="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={item.refresh.progress_percent}
                        >
                            <div
                                className="h-full rounded-full bg-primary transition-[width]"
                                style={{
                                    width: `${item.refresh.progress_percent}%`,
                                }}
                            />
                        </div>
                    </div>
                )}
                {item.refresh?.status === 'partial' && (
                    <PartialDataBanner
                        title="Partial observation"
                        description={
                            item.refresh.warnings.join(' ') ||
                            'Some public metrics were unavailable; missing values remain unavailable rather than zero.'
                        }
                    />
                )}
                {item.refresh?.status === 'failed' && (
                    <StatePanel
                        title={
                            item.refresh.quota_exhausted
                                ? 'YouTube quota exhausted'
                                : 'Refresh failed'
                        }
                        description={
                            item.refresh.quota_exhausted
                                ? 'Wait for the Pacific-time quota reset, then retry. The previous observation is preserved.'
                                : (item.refresh.error_message ??
                                  'The previous observation is preserved and this refresh can be retried.')
                        }
                        action={
                            <Form
                                action={`/watchlist/refreshes/${item.refresh.public_id}/retry`}
                                method="post"
                            >
                                <Button type="submit" size="sm">
                                    Retry
                                </Button>
                            </Form>
                        }
                    />
                )}
                {deltas.length > 0 && (
                    <div>
                        <p className="text-sm font-medium">
                            Change since previous Watchlist observation
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {deltas.map(([label, value]) => (
                                <Badge key={label} variant="outline">
                                    {label}: {(value as number) > 0 ? '+' : ''}
                                    {Number(value).toLocaleString()}
                                </Badge>
                            ))}
                        </div>
                    </div>
                )}
                {!item.refresh && (
                    <p className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                        Ready for a manual observation. Refresh is queued and
                        quota-aware; loading this page never calls YouTube.
                    </p>
                )}
                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="grid gap-1.5 text-sm font-medium">
                        Status
                        <Select
                            value={form.data.status}
                            onChange={(value) =>
                                form.setData(
                                    'status',
                                    value as WatchlistItem['status'],
                                )
                            }
                            options={[
                                'monitoring',
                                'attention',
                                'promising',
                                'ruled_out',
                            ]}
                        />
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium">
                        Project
                        <select
                            value={form.data.project}
                            onChange={(e) =>
                                form.setData('project', e.target.value)
                            }
                            className="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="">No project</option>
                            {projects.map((project) => (
                                <option
                                    key={project.public_id}
                                    value={project.public_id}
                                >
                                    {project.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
                <label className="grid gap-1.5 text-sm font-medium">
                    Topic Workspace
                    <select
                        value={form.data.workspace}
                        onChange={(e) =>
                            form.setData('workspace', e.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">No workspace</option>
                        {workspaces.map((workspace) => (
                            <option
                                key={workspace.public_id}
                                value={workspace.public_id}
                            >
                                {workspace.name} · {workspace.market_key}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1.5 text-sm font-medium">
                    Private watch note
                    <textarea
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        rows={3}
                        maxLength={10000}
                        className="rounded-md border bg-background p-2 font-normal"
                    />
                </label>
                {item.tags.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {item.tags.map((tag) => (
                            <Badge key={tag.public_id} variant="outline">
                                {tag.name}
                            </Badge>
                        ))}
                    </div>
                )}
                {tags.length > 0 && item.target_reference && (
                    <div>
                        <p className="text-sm font-medium">Tags</p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {tags.map((tag) => {
                                const attached = item.tags.some(
                                    (entry) =>
                                        entry.public_id === tag.public_id,
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
                                            value={item.target_type}
                                        />
                                        <input
                                            type="hidden"
                                            name="target_reference"
                                            value={item.target_reference ?? ''}
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant={
                                                attached
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {attached ? 'Remove ' : 'Add '}
                                            {tag.name}
                                        </Button>
                                    </Form>
                                );
                            })}
                        </div>
                    </div>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        onClick={save}
                        disabled={!form.isDirty || form.processing}
                    >
                        Save
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.patch(
                                `/watchlist/${item.public_id}`,
                                { ...form.data, is_active: !item.is_active },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {item.is_active ? <Pause /> : <Play />}
                        {item.is_active ? 'Pause' : 'Resume'}
                    </Button>
                    <Form
                        action={`/watchlist/${item.public_id}/refresh`}
                        method="post"
                        disableWhileProcessing
                    >
                        <input
                            type="hidden"
                            name="mode"
                            value="force_refresh"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={
                                !item.is_active || item.refresh?.is_active
                            }
                        >
                            <RefreshCw />
                            Refresh now
                        </Button>
                    </Form>
                    {item.analyzer_url && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={item.analyzer_url}>Analyzer</Link>
                        </Button>
                    )}
                    {item.youtube_url && (
                        <Button asChild size="sm" variant="ghost">
                            <a
                                href={item.youtube_url}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                YouTube <ArrowUpRight />
                            </a>
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => setConfirmRemove(true)}
                    >
                        <Trash2 />
                        Remove
                    </Button>
                </div>
            </CardContent>
            <Dialog open={confirmRemove} onOpenChange={setConfirmRemove}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Remove “{item.label}” from Watchlist?
                        </DialogTitle>
                        <DialogDescription>
                            This stops monitoring and removes Watchlist refresh
                            history. It does not remove Favorites, canonical
                            YouTube entities, or retained Analyzer observations.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Form
                            action={`/watchlist/${item.public_id}`}
                            method="delete"
                        >
                            <Button type="submit" variant="destructive">
                                Remove watched item
                            </Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function Select({
    value,
    options,
    onChange,
}: {
    value: string;
    options: string[];
    onChange: (value: string) => void;
}) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-10 rounded-md border bg-background px-3 capitalize"
        >
            {options.map((option) => (
                <option key={option} value={option}>
                    {option.replace('_', ' ')}
                </option>
            ))}
        </select>
    );
}
