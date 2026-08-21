import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    ArrowUpRight,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
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
import type {
    TopicEvidenceItem,
    TopicEvidenceRole,
    TopicEvidenceType,
} from '@/types';

type Choice = { reference: string; label: string; market_key: string | null };
type Props = {
    workspace: {
        public_id: string;
        name: string;
        description: string | null;
        market_key: string;
        region_code: string | null;
        language: string;
        archived_at: string | null;
        project: { public_id: string; name: string } | null;
    };
    filters: { role: string; type: string };
    items: TopicEvidenceItem[];
    available_evidence: Record<TopicEvidenceType, Choice[]>;
    completed_research_runs: { public_id: string; label: string }[];
    launches: {
        type: string;
        url: string | null;
        label: string;
        status: string | null;
        created_at: string | null;
    }[];
    projects: { public_id: string; name: string }[];
    decision_canvas: {
        workspace_note: string | null;
        coverage: {
            linked_count: number;
            unavailable_count: number;
            cross_market_count: number;
            same_market_completed_run_count: number;
            roles: Partial<Record<TopicEvidenceRole, number>>;
        };
        warnings: string[];
        next_action: {
            label: string;
            description: string;
            href: string | null;
        };
    };
};

const types: TopicEvidenceType[] = [
    'video',
    'channel',
    'research_query',
    'research_run',
    'niche_candidate',
    'analyzer_run',
    'watchlist_item',
];
const roles: TopicEvidenceRole[] = [
    'evidence',
    'example',
    'outlier',
    'competitor',
    'inspiration',
    'counterexample',
];

export default function TopicWorkspaceShow(props: Props) {
    const archived = Boolean(props.workspace.archived_at);
    const [filters, setFilters] = useState(props.filters);
    const [searchOpen, setSearchOpen] = useState(false);
    const [discoverOpen, setDiscoverOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const edit = useForm({
        name: props.workspace.name,
        description: props.workspace.description ?? '',
        market_key: props.workspace.market_key,
        project: props.workspace.project?.public_id ?? '',
    });
    const evidence = useForm<{
        target_type: TopicEvidenceType;
        target_reference: string;
        evidence_role: TopicEvidenceRole;
        note: string;
    }>({
        target_type: 'video',
        target_reference: '',
        evidence_role: 'evidence',
        note: '',
    });
    const choices = props.available_evidence[evidence.data.target_type] ?? [];
    const applyFilters = () =>
        router.get(`/topics/${props.workspace.public_id}`, filters, {
            preserveState: true,
            replace: true,
        });

    return (
        <>
            <Head title={props.workspace.name} />
            <PageContainer>
                <PageHeader
                    eyebrow="Topic Workspace"
                    title={props.workspace.name}
                    description="A market-scoped evidence hub. Links point to canonical stored records; metric payloads are never copied."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href="/topics">All workspaces</Link>
                            </Button>
                            {archived ? (
                                <Form
                                    action={`/topics/${props.workspace.public_id}/restore`}
                                    method="post"
                                >
                                    <Button type="submit">Restore</Button>
                                </Form>
                            ) : (
                                <Button
                                    variant="destructive"
                                    onClick={() => setArchiveOpen(true)}
                                >
                                    <Archive />
                                    Archive
                                </Button>
                            )}
                        </>
                    }
                />
                <div className="flex flex-wrap gap-2">
                    <Badge>{props.workspace.market_key}</Badge>
                    <Badge variant="outline">
                        Language: {props.workspace.language}
                    </Badge>
                    <Badge variant="outline">
                        Region: {props.workspace.region_code ?? 'Global sample'}
                    </Badge>
                    {archived && (
                        <Badge variant="secondary">
                            Read-only archived state
                        </Badge>
                    )}
                </div>
                {archived && (
                    <StatePanel
                        icon={Archive}
                        title="This workspace is archived"
                        description="Evidence and launch history remain readable. Restore it before editing, adding evidence, or launching collection."
                    />
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Decision canvas</CardTitle>
                        <CardDescription>
                            A concise view of linked stored evidence and the
                            safest next workflow step. It is not a
                            recommendation or causal result.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-start">
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-medium">
                                Workspace note
                            </p>
                            <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                {props.decision_canvas.workspace_note ??
                                    'No workspace note has been recorded.'}
                            </p>
                        </div>
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-medium">
                                Evidence coverage
                            </p>
                            <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <Coverage
                                    label="Linked"
                                    value={
                                        props.decision_canvas.coverage
                                            .linked_count
                                    }
                                />
                                <Coverage
                                    label="Same-market Searches"
                                    value={
                                        props.decision_canvas.coverage
                                            .same_market_completed_run_count
                                    }
                                />
                                <Coverage
                                    label="Cross-market"
                                    value={
                                        props.decision_canvas.coverage
                                            .cross_market_count
                                    }
                                />
                                <Coverage
                                    label="Unavailable"
                                    value={
                                        props.decision_canvas.coverage
                                            .unavailable_count
                                    }
                                />
                            </dl>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {Object.entries(
                                    props.decision_canvas.coverage.roles,
                                ).map(([role, count]) => (
                                    <Badge key={role} variant="outline">
                                        {role}: {count}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                        <div className="rounded-lg border border-primary/25 bg-primary/5 p-4">
                            <p className="text-sm font-medium">Next action</p>
                            <p className="mt-2 font-semibold">
                                {props.decision_canvas.next_action.label}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {props.decision_canvas.next_action.description}
                            </p>
                            {props.decision_canvas.next_action.href &&
                                !archived && (
                                    <Button asChild className="mt-4" size="sm">
                                        <a
                                            href={
                                                props.decision_canvas
                                                    .next_action.href
                                            }
                                        >
                                            {
                                                props.decision_canvas
                                                    .next_action.label
                                            }
                                        </a>
                                    </Button>
                                )}
                        </div>
                    </CardContent>
                    {props.decision_canvas.warnings.length > 0 && (
                        <CardContent className="pt-0">
                            <div
                                className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4"
                                role="status"
                            >
                                <p className="font-medium">
                                    Evidence needs review
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                    {props.decision_canvas.warnings.map(
                                        (warning) => (
                                            <li key={warning}>{warning}</li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </CardContent>
                    )}
                </Card>
                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.65fr)]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Evidence</CardTitle>
                                <CardDescription>
                                    Filter by explicit type and research role.
                                    Cross-market sources remain linked with a
                                    warning.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-3">
                                <Select
                                    value={filters.type}
                                    options={['all', ...types]}
                                    onChange={(type) =>
                                        setFilters({ ...filters, type })
                                    }
                                />
                                <Select
                                    value={filters.role}
                                    options={['all', ...roles]}
                                    onChange={(role) =>
                                        setFilters({ ...filters, role })
                                    }
                                />
                                <Button onClick={applyFilters}>
                                    Apply filters
                                </Button>
                            </CardContent>
                        </Card>
                        {props.items.length === 0 ? (
                            <StatePanel
                                title="No matching evidence"
                                description={
                                    archived
                                        ? 'This archived workspace has no evidence matching these filters.'
                                        : 'Link a stored record from the evidence form. Adding a link consumes no YouTube quota.'
                                }
                            />
                        ) : (
                            <div className="grid gap-3">
                                {props.items.map((item) => (
                                    <Card key={item.id}>
                                        <CardContent className="pt-5">
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap gap-2">
                                                        <Badge>
                                                            {item.type.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            {item.role}
                                                        </Badge>
                                                        {item.market_key && (
                                                            <Badge variant="secondary">
                                                                {
                                                                    item.market_key
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="mt-2 font-semibold break-words">
                                                        {item.label}
                                                    </p>
                                                    {item.detected_topic_profile && (
                                                        <div className="mt-2 flex flex-wrap gap-2">
                                                            <Badge variant="outline">
                                                                Inferred
                                                            </Badge>
                                                            <Badge variant="secondary">
                                                                {
                                                                    item
                                                                        .detected_topic_profile
                                                                        .niche
                                                                }
                                                            </Badge>
                                                            <span className="self-center text-xs text-muted-foreground">
                                                                {
                                                                    item
                                                                        .detected_topic_profile
                                                                        .language
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    item
                                                                        .detected_topic_profile
                                                                        .version
                                                                }
                                                            </span>
                                                        </div>
                                                    )}
                                                    {item.note && (
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {item.note}
                                                        </p>
                                                    )}
                                                    {item.cross_market_warning && (
                                                        <p className="mt-2 flex gap-2 text-sm text-amber-700 dark:text-amber-300">
                                                            <AlertTriangle className="size-4 shrink-0" />
                                                            {
                                                                item.cross_market_warning
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex shrink-0 gap-2">
                                                    {item.url && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            {item.url.startsWith(
                                                                'http',
                                                            ) ? (
                                                                <a
                                                                    href={
                                                                        item.url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer noopener"
                                                                >
                                                                    Open{' '}
                                                                    <ArrowUpRight />
                                                                </a>
                                                            ) : (
                                                                <Link
                                                                    href={
                                                                        item.url
                                                                    }
                                                                >
                                                                    Open{' '}
                                                                    <ArrowUpRight />
                                                                </Link>
                                                            )}
                                                        </Button>
                                                    )}
                                                    {!archived && (
                                                        <Form
                                                            action={`/topics/${props.workspace.public_id}/evidence/${item.id}`}
                                                            method="delete"
                                                        >
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="ghost"
                                                                aria-label={`Remove ${item.label}`}
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </Form>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="space-y-4">
                        {!archived && (
                            <Card id="link-evidence">
                                <CardHeader>
                                    <CardTitle>
                                        Link existing evidence
                                    </CardTitle>
                                    <CardDescription>
                                        Select an owner-visible canonical record
                                        and assign its role. This stores only
                                        its typed reference.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="grid gap-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            evidence.post(
                                                `/topics/${props.workspace.public_id}/evidence`,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        evidence.reset(
                                                            'target_reference',
                                                            'note',
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <label className="grid gap-1.5 text-sm font-medium">
                                            Evidence type
                                            <Select
                                                value={
                                                    evidence.data.target_type
                                                }
                                                options={types}
                                                onChange={(value) =>
                                                    evidence.setData(
                                                        (data) => ({
                                                            ...data,
                                                            target_type:
                                                                value as TopicEvidenceType,
                                                            target_reference:
                                                                '',
                                                        }),
                                                    )
                                                }
                                            />
                                        </label>
                                        <label className="grid gap-1.5 text-sm font-medium">
                                            Stored record
                                            <select
                                                className="h-10 rounded-md border bg-background px-3"
                                                value={
                                                    evidence.data
                                                        .target_reference
                                                }
                                                onChange={(e) =>
                                                    evidence.setData(
                                                        'target_reference',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Select stored evidence
                                                </option>
                                                {choices.map((choice) => (
                                                    <option
                                                        key={choice.reference}
                                                        value={choice.reference}
                                                    >
                                                        {choice.label}
                                                        {choice.market_key
                                                            ? ` · ${choice.market_key}`
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        {choices.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                No owner-visible records of this
                                                type are available yet.
                                            </p>
                                        )}
                                        <label className="grid gap-1.5 text-sm font-medium">
                                            Evidence role
                                            <Select
                                                value={
                                                    evidence.data.evidence_role
                                                }
                                                options={roles}
                                                onChange={(value) =>
                                                    evidence.setData(
                                                        'evidence_role',
                                                        value as TopicEvidenceRole,
                                                    )
                                                }
                                            />
                                        </label>
                                        <label className="grid gap-1.5 text-sm font-medium">
                                            Workspace note
                                            <textarea
                                                className="rounded-md border bg-background p-2"
                                                rows={3}
                                                maxLength={10000}
                                                value={evidence.data.note}
                                                onChange={(e) =>
                                                    evidence.setData(
                                                        'note',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                        {Object.values(evidence.errors).map(
                                            (error) => (
                                                <p
                                                    key={error}
                                                    className="text-sm text-destructive"
                                                >
                                                    {error}
                                                </p>
                                            ),
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={
                                                evidence.processing ||
                                                !evidence.data.target_reference
                                            }
                                        >
                                            Link evidence
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                        {!archived && (
                            <Card id="launch-search">
                                <CardHeader>
                                    <CardTitle>Launch research</CardTitle>
                                    <CardDescription>
                                        Both actions are explicit and
                                        quota-aware. Search uses the frozen
                                        workspace market; Discover requires a
                                        completed same-market Search evidence
                                        sample.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    <Button onClick={() => setSearchOpen(true)}>
                                        <Search />
                                        Search this topic
                                    </Button>
                                    <Button
                                        id="launch-discovery"
                                        variant="outline"
                                        onClick={() => setDiscoverOpen(true)}
                                        disabled={
                                            props.completed_research_runs
                                                .length === 0
                                        }
                                    >
                                        <Sparkles />
                                        Discover related candidates
                                    </Button>
                                    {props.completed_research_runs.length ===
                                        0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Complete and link a same-market
                                            Search run first.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle>Workspace details</CardTitle>
                                <CardDescription>
                                    The primary market is immutable; archive
                                    instead of repurposing market context.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="grid gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        edit.patch(
                                            `/topics/${props.workspace.public_id}`,
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Name
                                        <Input
                                            value={edit.data.name}
                                            onChange={(e) =>
                                                edit.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={archived}
                                        />
                                    </label>
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Description
                                        <textarea
                                            className="rounded-md border bg-background p-2"
                                            rows={4}
                                            value={edit.data.description}
                                            onChange={(e) =>
                                                edit.setData(
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={archived}
                                        />
                                    </label>
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Project
                                        <select
                                            className="h-10 rounded-md border bg-background px-3"
                                            value={edit.data.project}
                                            onChange={(e) =>
                                                edit.setData(
                                                    'project',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={archived}
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
                                    {Object.values(edit.errors).map((error) => (
                                        <p
                                            key={error}
                                            className="text-sm text-destructive"
                                        >
                                            {error}
                                        </p>
                                    ))}
                                    <Button
                                        type="submit"
                                        disabled={
                                            archived ||
                                            !edit.isDirty ||
                                            edit.processing
                                        }
                                    >
                                        Save details
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Linked workflow history</CardTitle>
                        <CardDescription>
                            Immutable Search and Discovery outputs launched from
                            this workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {props.launches.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No workflows launched from this workspace yet.
                            </p>
                        ) : (
                            <div className="grid gap-2">
                                {props.launches.map((launch, index) => (
                                    <div
                                        key={`${launch.type}-${launch.created_at}-${index}`}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                                    >
                                        <div>
                                            <Badge variant="outline">
                                                {launch.type}
                                            </Badge>
                                            <p className="mt-1 font-medium">
                                                {launch.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {launch.created_at
                                                    ? new Date(
                                                          launch.created_at,
                                                      ).toLocaleString()
                                                    : 'Time unavailable'}{' '}
                                                ·{' '}
                                                {launch.status ??
                                                    'status unavailable'}
                                            </p>
                                        </div>
                                        {launch.url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={launch.url}>
                                                    Open result
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </PageContainer>
            <SearchDialog
                open={searchOpen}
                onOpenChange={setSearchOpen}
                workspace={props.workspace}
            />
            <DiscoverDialog
                open={discoverOpen}
                onOpenChange={setDiscoverOpen}
                workspace={props.workspace}
                runs={props.completed_research_runs}
            />
            <Dialog open={archiveOpen} onOpenChange={setArchiveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Archive “{props.workspace.name}”?
                        </DialogTitle>
                        <DialogDescription>
                            This makes the workspace read-only and blocks new
                            launches. Evidence and source records are preserved.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Form
                            action={`/topics/${props.workspace.public_id}/archive`}
                            method="post"
                        >
                            <Button type="submit" variant="destructive">
                                Archive workspace
                            </Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function SearchDialog({
    open,
    onOpenChange,
    workspace,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Props['workspace'];
}) {
    const form = useForm({ confirmed: true, query_text: workspace.name });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Search this topic?</DialogTitle>
                    <DialogDescription>
                        This queues YouTube collection in {workspace.market_key}
                        . The immutable Search run will be linked back as
                        evidence.
                    </DialogDescription>
                </DialogHeader>
                <label className="grid gap-1.5 text-sm font-medium">
                    Query
                    <Input
                        value={form.data.query_text}
                        onChange={(e) =>
                            form.setData('query_text', e.target.value)
                        }
                    />
                </label>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={() =>
                            form.post(
                                `/topics/${workspace.public_id}/launch-search`,
                            )
                        }
                        disabled={form.processing}
                    >
                        Confirm and queue Search
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DiscoverDialog({
    open,
    onOpenChange,
    workspace,
    runs,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Props['workspace'];
    runs: Props['completed_research_runs'];
}) {
    const form = useForm({
        confirmed: true,
        research_run: runs[0]?.public_id ?? '',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Discover related candidates?</DialogTitle>
                    <DialogDescription>
                        This analyzes a stored same-market Search sample and
                        queues Discovery. Loading or filtering the workspace
                        itself consumes no quota.
                    </DialogDescription>
                </DialogHeader>
                <label className="grid gap-1.5 text-sm font-medium">
                    Evidence sample
                    <select
                        className="h-10 rounded-md border bg-background px-3"
                        value={form.data.research_run}
                        onChange={(e) =>
                            form.setData('research_run', e.target.value)
                        }
                    >
                        {runs.map((run) => (
                            <option key={run.public_id} value={run.public_id}>
                                {run.label}
                            </option>
                        ))}
                    </select>
                </label>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={() =>
                            form.post(
                                `/topics/${workspace.public_id}/launch-discovery`,
                            )
                        }
                        disabled={form.processing || !form.data.research_run}
                    >
                        Confirm and queue Discover
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Coverage({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-semibold tabular-nums">{value}</dd>
        </div>
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
            className="h-10 rounded-md border bg-background px-3 capitalize"
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            {options.map((option) => (
                <option key={option} value={option}>
                    {option.replaceAll('_', ' ')}
                </option>
            ))}
        </select>
    );
}
