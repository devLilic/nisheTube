import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    FolderKanban,
    Grid2X2,
    List,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import type { PaginationMeta } from '@/components/pagination-controls';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { LibraryProject } from '@/types';

type Props = {
    projects: LibraryProject[];
    pagination: PaginationMeta;
    filters: {
        search: string;
        status: string;
        sort: string;
        view: 'grid' | 'list';
    };
};

export default function ProjectsIndex({
    projects,
    pagination,
    filters,
}: Props) {
    const [creating, setCreating] = useState(false);
    const updateFilter = (key: string, value: string) =>
        router.get(
            '/projects',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Projects" />
            <PageContainer>
                <PageHeader
                    eyebrow="Library"
                    title="Projects"
                    description="Group saved queries, discovery work, videos, channels, and notes into focused research collections."
                    actions={
                        <Button onClick={() => setCreating((value) => !value)}>
                            <Plus />
                            New project
                        </Button>
                    }
                />

                {creating && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Create project
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/library/projects"
                                method="post"
                                disableWhileProcessing
                                className="grid gap-3 md:grid-cols-[1fr_2fr_9rem_auto]"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div>
                                            <Input
                                                name="name"
                                                placeholder="Project name"
                                                required
                                                maxLength={120}
                                            />
                                            <p className="mt-1 text-xs text-destructive">
                                                {errors.name}
                                            </p>
                                        </div>
                                        <Input
                                            name="description"
                                            placeholder="Optional description"
                                            maxLength={2000}
                                        />
                                        <Input
                                            name="color"
                                            type="color"
                                            defaultValue="#2563EB"
                                            aria-label="Project color"
                                        />
                                        <Button disabled={processing}>
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Plus />
                                            )}
                                            Create
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <Card className="py-4">
                    <CardContent className="flex flex-col gap-3 px-4 md:flex-row md:items-center">
                        <label className="relative flex-1">
                            <span className="sr-only">Search projects</span>
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                defaultValue={filters.search}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        updateFilter(
                                            'search',
                                            event.currentTarget.value,
                                        );
                                    }
                                }}
                                placeholder="Search name or description…"
                                className="pl-9"
                            />
                        </label>
                        <select
                            value={filters.status}
                            onChange={(event) =>
                                updateFilter('status', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            aria-label="Project status"
                        >
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                            <option value="all">All</option>
                        </select>
                        <select
                            value={filters.sort}
                            onChange={(event) =>
                                updateFilter('sort', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            aria-label="Sort projects"
                        >
                            <option value="updated">Recently updated</option>
                            <option value="name">Name</option>
                            <option value="oldest">Oldest</option>
                        </select>
                        <div className="flex rounded-md border p-1">
                            <Button
                                size="icon"
                                variant={
                                    filters.view === 'grid'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                onClick={() => updateFilter('view', 'grid')}
                                aria-label="Grid view"
                            >
                                <Grid2X2 />
                            </Button>
                            <Button
                                size="icon"
                                variant={
                                    filters.view === 'list'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                onClick={() => updateFilter('view', 'list')}
                                aria-label="List view"
                            >
                                <List />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {projects.length === 0 ? (
                    <StatePanel
                        title="No projects found"
                        description={
                            filters.search || filters.status === 'archived'
                                ? 'Adjust the filters or create a new project.'
                                : 'Create a project to organize research and saved evidence.'
                        }
                        icon={FolderKanban}
                        action={
                            <Button onClick={() => setCreating(true)}>
                                <Plus />
                                Create project
                            </Button>
                        }
                    />
                ) : (
                    <div
                        className={
                            filters.view === 'grid'
                                ? 'grid gap-4 lg:grid-cols-2 xl:grid-cols-3'
                                : 'space-y-3'
                        }
                    >
                        {projects.map((project) => (
                            <Card
                                key={project.public_id}
                                className="overflow-hidden"
                            >
                                <div
                                    className="h-1.5"
                                    style={{
                                        backgroundColor:
                                            project.color ?? undefined,
                                    }}
                                />
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                <Link
                                                    href={`/projects/${project.public_id}`}
                                                    className="hover:text-primary hover:underline"
                                                >
                                                    {project.name}
                                                </Link>
                                            </CardTitle>
                                            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                {project.description ||
                                                    'No description yet.'}
                                            </p>
                                        </div>
                                        {project.archived && (
                                            <Badge variant="outline">
                                                <Archive />
                                                Archived
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary">
                                        {project.query_count} queries
                                    </Badge>
                                    <Badge variant="secondary">
                                        {project.favorite_count} favorites
                                    </Badge>
                                    <Badge variant="secondary">
                                        {project.discovery_count} discoveries
                                    </Badge>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <PaginationControls
                    pagination={pagination}
                    onPageChange={(page) =>
                        router.get(
                            '/projects',
                            { ...filters, page },
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />
            </PageContainer>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
