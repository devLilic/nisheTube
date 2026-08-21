import { Form, Head, Link } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    Compass,
    Heart,
    RotateCcw,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { FavoriteCard } from '@/features/library/favorite-card';
import { formatDate } from '@/lib/formatters';
import type {
    Auth,
    LibraryContext,
    LibraryFavorite,
    LibraryProject,
} from '@/types';

type ProjectDetail = LibraryProject & {
    queries: Array<{
        public_id: string;
        label: string;
        market: string;
        run_count: number;
        latest_run: {
            public_id: string;
            status: string;
            completed_at: string | null;
        } | null;
    }>;
    discovery_runs: Array<{
        public_id: string;
        status: string;
        market: string;
        candidate_count: number;
        created_at: string | null;
    }>;
    favorites: LibraryFavorite[];
    workspaces: Array<{
        public_id: string;
        name: string;
        market_key: string;
        archived: boolean;
        updated_at: string | null;
    }>;
    shortlist: Array<{ public_id: string; label: string }>;
};

export default function ProjectShow({
    project,
    library,
    markets,
    auth,
}: {
    project: ProjectDetail;
    library: LibraryContext;
    markets: Array<{ key: string; name: string }>;
    auth: Auth;
}) {
    const [tab, setTab] = useState<
        'queries' | 'favorites' | 'discoveries' | 'decision' | 'notes'
    >('queries');
    const [confirming, setConfirming] = useState<'archive' | 'delete' | null>(
        null,
    );
    const tabs = [
        ['queries', `Queries (${project.queries.length})`],
        ['favorites', `Favorites (${project.favorites.length})`],
        ['discoveries', `Discovery (${project.discovery_runs.length})`],
        ['decision', 'Decision context'],
        ['notes', 'Project notes'],
    ] as const;

    return (
        <>
            <Head title={project.name} />
            <PageContainer>
                <PageHeader
                    eyebrow="Project"
                    title={project.name}
                    description={
                        project.description ||
                        'Organize related research, discoveries, and saved evidence.'
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/projects">
                                    <ArrowLeft />
                                    All projects
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('archive')}
                            >
                                {project.archived ? <RotateCcw /> : <Archive />}
                                {project.archived ? 'Restore' : 'Archive'}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => setConfirming('delete')}
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        </>
                    }
                />

                <div
                    className="flex overflow-x-auto rounded-lg border bg-muted/30 p-1"
                    role="tablist"
                    aria-label="Project sections"
                >
                    {tabs.map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            onClick={() => setTab(value)}
                            className="shrink-0 rounded-md px-4 py-2 text-sm font-medium aria-selected:bg-background aria-selected:shadow-sm"
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'queries' &&
                    (project.queries.length === 0 ? (
                        <StatePanel
                            title="No saved queries"
                            description="Assign a search query to this project when saving or organizing research."
                            icon={Search}
                            action={
                                <Button asChild>
                                    <Link href="/search">New search</Link>
                                </Button>
                            }
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {project.queries.map((query) => (
                                <Card key={query.public_id}>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {query.label}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {query.market}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {query.run_count} runs
                                        </Badge>
                                        {query.latest_run && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/research/runs/${query.latest_run.public_id}`}
                                                >
                                                    Open latest ·{' '}
                                                    {query.latest_run.status}
                                                </Link>
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ))}
                {tab === 'favorites' &&
                    (project.favorites.length === 0 ? (
                        <StatePanel
                            title="No project favorites"
                            description="Move favorites into this project from the Favorites page."
                            icon={Heart}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href="/favorites">
                                        Browse favorites
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                            {project.favorites.map((favorite) => (
                                <FavoriteCard
                                    key={favorite.public_id}
                                    favorite={favorite}
                                    library={library}
                                />
                            ))}
                        </div>
                    ))}
                {tab === 'discoveries' &&
                    (project.discovery_runs.length === 0 ? (
                        <StatePanel
                            title="No discovery runs"
                            description="Discovery runs associated with this project will appear here."
                            icon={Compass}
                            action={
                                <Button asChild>
                                    <Link href="/discover">
                                        Start discovery
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {project.discovery_runs.map((run) => (
                                <Card key={run.public_id}>
                                    <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                                        <div>
                                            <p className="font-medium">
                                                {run.market}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {run.candidate_count} candidates
                                            </p>
                                        </div>
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={`/discover/runs/${run.public_id}`}
                                            >
                                                Open · {run.status}
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ))}
                {tab === 'decision' && (
                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Project decision context
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={`/library/projects/${project.public_id}`}
                                    method="patch"
                                    disableWhileProcessing
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <label className="grid gap-1 text-sm font-medium">
                                                    Decision status
                                                    <select
                                                        name="decision_status"
                                                        defaultValue={
                                                            project.decision_status
                                                        }
                                                        className="h-10 rounded-md border bg-background px-3 capitalize"
                                                    >
                                                        {[
                                                            'exploring',
                                                            'active',
                                                            'decided',
                                                            'paused',
                                                        ].map((status) => (
                                                            <option
                                                                key={status}
                                                                value={status}
                                                            >
                                                                {status}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <label className="grid gap-1 text-sm font-medium">
                                                    Primary market
                                                    <select
                                                        name="market_key"
                                                        defaultValue={
                                                            project.market_key ??
                                                            ''
                                                        }
                                                        className="h-10 rounded-md border bg-background px-3"
                                                    >
                                                        <option value="">
                                                            No primary market
                                                        </option>
                                                        {markets.map(
                                                            (market) => (
                                                                <option
                                                                    key={
                                                                        market.key
                                                                    }
                                                                    value={
                                                                        market.key
                                                                    }
                                                                >
                                                                    {
                                                                        market.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </label>
                                            </div>
                                            <label className="grid gap-1 text-sm font-medium">
                                                Purpose
                                                <textarea
                                                    name="purpose"
                                                    defaultValue={
                                                        project.purpose ?? ''
                                                    }
                                                    rows={3}
                                                    maxLength={2000}
                                                    className="rounded-md border bg-background p-2 font-normal"
                                                />
                                            </label>
                                            <label className="grid gap-1 text-sm font-medium">
                                                Themes
                                                <textarea
                                                    name="themes"
                                                    defaultValue={project.themes.join(
                                                        ', ',
                                                    )}
                                                    rows={2}
                                                    maxLength={1000}
                                                    placeholder="Comma-separated themes"
                                                    className="rounded-md border bg-background p-2 font-normal"
                                                />
                                            </label>
                                            <label className="grid gap-1 text-sm font-medium">
                                                Decision note
                                                <textarea
                                                    name="decision_note"
                                                    defaultValue={
                                                        project.decision_note ??
                                                        ''
                                                    }
                                                    rows={4}
                                                    maxLength={4000}
                                                    className="rounded-md border bg-background p-2 font-normal"
                                                />
                                            </label>
                                            <input
                                                type="hidden"
                                                name="name"
                                                value={project.name}
                                            />
                                            <input
                                                type="hidden"
                                                name="description"
                                                value={
                                                    project.description ?? ''
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="color"
                                                value={project.color ?? ''}
                                            />
                                            {Object.keys(errors).length > 0 && (
                                                <p
                                                    className="text-sm text-destructive"
                                                    role="alert"
                                                >
                                                    {errors.market_key ||
                                                        errors.themes ||
                                                        'The project context could not be updated.'}
                                                </p>
                                            )}
                                            <Button disabled={processing}>
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Save />
                                                )}
                                                Save decision context
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Stored context
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge>{project.decision_status}</Badge>
                                        {project.market_key && (
                                            <Badge variant="outline">
                                                {project.market_key}
                                            </Badge>
                                        )}
                                    </div>
                                    {project.themes.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {project.themes.map((theme) => (
                                                <Badge
                                                    key={theme}
                                                    variant="secondary"
                                                >
                                                    {theme}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            No themes recorded yet.
                                        </p>
                                    )}
                                    {project.purpose && (
                                        <p className="whitespace-pre-wrap">
                                            {project.purpose}
                                        </p>
                                    )}
                                    {project.decision_note && (
                                        <p className="whitespace-pre-wrap text-muted-foreground">
                                            {project.decision_note}
                                        </p>
                                    )}
                                    {project.updated_at && (
                                        <p className="text-xs text-muted-foreground">
                                            Last project activity:{' '}
                                            {formatDate(
                                                project.updated_at,
                                                auth.user.timezone,
                                            )}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Linked workspaces
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {project.workspaces.length === 0 ? (
                                        <p className="text-muted-foreground">
                                            No linked workspaces yet.
                                        </p>
                                    ) : (
                                        project.workspaces.map((workspace) => (
                                            <Link
                                                key={workspace.public_id}
                                                href={`/topics/${workspace.public_id}`}
                                                className="block rounded-md border p-2 hover:bg-muted"
                                            >
                                                {workspace.name} ·{' '}
                                                {workspace.market_key}
                                                {workspace.archived
                                                    ? ' · archived'
                                                    : ''}
                                            </Link>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Project Shortlist
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {project.shortlist.length === 0 ? (
                                        <p className="text-muted-foreground">
                                            No saved research runs in this
                                            project yet.
                                        </p>
                                    ) : (
                                        project.shortlist.map((item) => (
                                            <p key={item.public_id}>
                                                {item.label}
                                            </p>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}
                {tab === 'notes' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Project details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`/library/projects/${project.public_id}`}
                                method="patch"
                                disableWhileProcessing
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label className="block text-sm font-medium">
                                            Name
                                            <Input
                                                name="name"
                                                defaultValue={project.name}
                                                maxLength={120}
                                                required
                                                className="mt-1"
                                            />
                                            <span className="text-xs text-destructive">
                                                {errors.name}
                                            </span>
                                        </label>
                                        <label className="block text-sm font-medium">
                                            Description
                                            <textarea
                                                name="description"
                                                defaultValue={
                                                    project.description ?? ''
                                                }
                                                rows={6}
                                                maxLength={2000}
                                                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            />
                                        </label>
                                        <label className="block text-sm font-medium">
                                            Color
                                            <Input
                                                name="color"
                                                type="color"
                                                defaultValue={
                                                    project.color ?? '#2563EB'
                                                }
                                                className="mt-1 w-28"
                                            />
                                        </label>
                                        <Button disabled={processing}>
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Save />
                                            )}
                                            Save project
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <Dialog
                    open={confirming !== null}
                    onOpenChange={(open) => !open && setConfirming(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {confirming === 'delete'
                                    ? 'Delete project?'
                                    : project.archived
                                      ? 'Restore project?'
                                      : 'Archive project?'}
                            </DialogTitle>
                            <DialogDescription>
                                {confirming === 'delete'
                                    ? `Delete “${project.name}”? Saved items will remain as favorites without this project, but this cannot be undone.`
                                    : project.archived
                                      ? `Restore “${project.name}” to the active project list?`
                                      : `Archive “${project.name}”? Its research and favorites remain preserved.`}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            {confirming && (
                                <Form
                                    action={
                                        confirming === 'delete'
                                            ? `/library/projects/${project.public_id}`
                                            : `/library/projects/${project.public_id}/${project.archived ? 'restore' : 'archive'}`
                                    }
                                    method={
                                        confirming === 'delete'
                                            ? 'delete'
                                            : 'post'
                                    }
                                    disableWhileProcessing
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant={
                                                confirming === 'delete'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : confirming === 'delete' ? (
                                                <Trash2 />
                                            ) : project.archived ? (
                                                <RotateCcw />
                                            ) : (
                                                <Archive />
                                            )}
                                            {confirming === 'delete'
                                                ? 'Delete project'
                                                : project.archived
                                                  ? 'Restore project'
                                                  : 'Archive project'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageContainer>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [
        { title: 'Projects', href: '/projects' },
        { title: 'Project', href: '#' },
    ],
};
