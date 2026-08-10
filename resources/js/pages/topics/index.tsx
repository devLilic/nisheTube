import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, FolderKanban, Plus } from 'lucide-react';
import { useState } from 'react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import type { PaginationMeta } from '@/components/pagination-controls';
import { PaginationControls } from '@/components/pagination-controls';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { TopicWorkspaceSummary } from '@/types';

type Props = {
    filters: { search: string; status: string; market: string };
    workspaces: TopicWorkspaceSummary[];
    pagination: PaginationMeta;
    markets: { key: string; name: string; relevance_language: string }[];
    projects: { public_id: string; name: string }[];
    counts: { all: number; active: number; archived: number };
};

export default function TopicWorkspaceIndex(props: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [filters, setFilters] = useState(props.filters);
    const form = useForm({
        name: '',
        description: '',
        market_key: props.markets[0]?.key ?? '',
        project: '',
    });
    const apply = () =>
        router.get('/topics', filters, { preserveState: true, replace: true });

    return (
        <>
            <Head title="Topic Workspaces" />
            <PageContainer>
                <PageHeader
                    title="Topic Workspaces"
                    description="Organize a topic by market and link canonical evidence without copying metric payloads."
                    actions={
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus />
                            New workspace
                        </Button>
                    }
                />
                <div className="grid gap-3 sm:grid-cols-3">
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
                        <CardTitle>Filter workspaces</CardTitle>
                        <CardDescription>
                            Filters use local stored context only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-4">
                        <Input
                            value={filters.search}
                            onChange={(e) =>
                                setFilters({
                                    ...filters,
                                    search: e.target.value,
                                })
                            }
                            placeholder="Search name or description"
                        />
                        <Select
                            value={filters.status}
                            onChange={(status) =>
                                setFilters({ ...filters, status })
                            }
                            options={['all', 'active', 'archived']}
                        />
                        <select
                            className="h-10 rounded-md border bg-background px-3"
                            value={filters.market}
                            onChange={(e) =>
                                setFilters({
                                    ...filters,
                                    market: e.target.value,
                                })
                            }
                        >
                            <option value="all">All markets</option>
                            {props.markets.map((market) => (
                                <option key={market.key} value={market.key}>
                                    {market.name}
                                </option>
                            ))}
                        </select>
                        <Button onClick={apply}>Apply filters</Button>
                    </CardContent>
                </Card>
                {props.workspaces.length === 0 ? (
                    <StatePanel
                        icon={FolderKanban}
                        title="No Topic Workspaces here"
                        description="Create one for a defined market, then add existing Search, Analyzer, Discovery, and Watchlist evidence."
                        action={
                            <Button onClick={() => setCreateOpen(true)}>
                                Create workspace
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {props.workspaces.map((workspace) => (
                            <Card key={workspace.public_id}>
                                <CardHeader>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge>{workspace.market_key}</Badge>
                                        <Badge variant="outline">
                                            {workspace.language}
                                        </Badge>
                                        {workspace.archived_at && (
                                            <Badge variant="secondary">
                                                <Archive />
                                                Archived
                                            </Badge>
                                        )}
                                    </div>
                                    <CardTitle className="mt-2">
                                        <Link
                                            className="hover:underline"
                                            href={`/topics/${workspace.public_id}`}
                                        >
                                            {workspace.name}
                                        </Link>
                                    </CardTitle>
                                    <CardDescription>
                                        {workspace.description ??
                                            'No workspace description yet.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between text-sm text-muted-foreground">
                                    <span>
                                        {workspace.items_count} evidence links
                                    </span>
                                    <span>
                                        {workspace.project?.name ??
                                            'No project'}
                                    </span>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <PaginationControls
                    pagination={props.pagination}
                    onPageChange={(page) =>
                        router.get(
                            '/topics',
                            { ...props.filters, page },
                            { preserveState: true },
                        )
                    }
                />
            </PageContainer>
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Topic Workspace</DialogTitle>
                        <DialogDescription>
                            The market context is frozen after creation so
                            evidence warnings remain meaningful.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/topics');
                        }}
                    >
                        <label className="grid gap-1.5 text-sm font-medium">
                            Topic name
                            <Input
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                maxLength={160}
                            />
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium">
                            Primary market
                            <select
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.market_key}
                                onChange={(e) =>
                                    form.setData('market_key', e.target.value)
                                }
                            >
                                {props.markets.map((m) => (
                                    <option key={m.key} value={m.key}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium">
                            Project
                            <select
                                className="h-10 rounded-md border bg-background px-3"
                                value={form.data.project}
                                onChange={(e) =>
                                    form.setData('project', e.target.value)
                                }
                            >
                                <option value="">No project</option>
                                {props.projects.map((p) => (
                                    <option
                                        key={p.public_id}
                                        value={p.public_id}
                                    >
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium">
                            Description
                            <textarea
                                className="rounded-md border bg-background p-2"
                                rows={4}
                                maxLength={10000}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </label>
                        {Object.values(form.errors).map((error) => (
                            <p key={error} className="text-sm text-destructive">
                                {error}
                            </p>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Create workspace
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Select({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: string[];
}) {
    return (
        <select
            className="h-10 rounded-md border bg-background px-3 capitalize"
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            {options.map((option) => (
                <option key={option} value={option}>
                    {option}
                </option>
            ))}
        </select>
    );
}
