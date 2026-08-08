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
import type { LibraryContext, LibraryFavorite, LibraryProject } from '@/types';

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
};

export default function ProjectShow({
    project,
    library,
}: {
    project: ProjectDetail;
    library: LibraryContext;
}) {
    const [tab, setTab] = useState<
        'queries' | 'favorites' | 'discoveries' | 'notes'
    >('queries');
    const [confirming, setConfirming] = useState<'archive' | 'delete' | null>(
        null,
    );
    const tabs = [
        ['queries', `Queries (${project.queries.length})`],
        ['favorites', `Favorites (${project.favorites.length})`],
        ['discoveries', `Discovery (${project.discovery_runs.length})`],
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
