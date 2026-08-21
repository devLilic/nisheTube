import { Link, router } from '@inertiajs/react';
import { GitCompareArrows, History, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MarketBadge } from '@/components/market-badge';
import { RunStatus } from '@/components/run-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { HistoryIndexData, HistoryRun } from '@/types';

function formatTimestamp(value: string | null, timezone: string) {
    return value
        ? new Intl.DateTimeFormat('en', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: timezone,
          }).format(new Date(value))
        : 'Not completed';
}

function readableParameter(key: string, value: unknown) {
    const labels: Record<string, string> = {
        search_order: 'Order',
        published_after: 'Published after',
        published_before: 'Published before',
        video_duration: 'Duration',
        video_category_id: 'Category',
        workflow_mode: 'Workflow',
        preset_key: 'Preset',
        language: 'Language',
        content_format: 'Content format',
        target_channel_size: 'Channel size',
    };
    const display =
        value === null || value === ''
            ? 'Any'
            : typeof value === 'boolean'
              ? value
                  ? 'Yes'
                  : 'No'
              : String(value).replaceAll('_', ' ');

    return `${labels[key] ?? key.replaceAll('_', ' ')}: ${display}`;
}

function FrozenParameters({ run }: { run: HistoryRun }) {
    const parameters = Object.entries(run.parameters);

    return (
        <ul className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
            {parameters.length === 0 ? (
                <li>Default collection parameters</li>
            ) : (
                parameters.map(([key, value]) => (
                    <li key={key}>{readableParameter(key, value)}</li>
                ))
            )}
            <li>Depth: {run.requested_result_count}</li>
        </ul>
    );
}

function matchingPair(runs: HistoryRun[]) {
    if (runs.length !== 2) {
        return {
            compatible: false,
            message: 'Select exactly two completed snapshots.',
        };
    }

    const [before, after] = runs;

    if (!before || !after || !before.can_compare || !after.can_compare) {
        return {
            compatible: false,
            message: 'Only completed snapshots can be compared.',
        };
    }

    if (
        before.query_text.trim().toLowerCase() !==
            after.query_text.trim().toLowerCase() ||
        before.market_key !== after.market_key ||
        before.kind !== after.kind
    ) {
        return {
            compatible: false,
            message:
                'These snapshots use different query, market, or collection kind and cannot be compared.',
        };
    }

    const parameterMatch =
        JSON.stringify(before.parameters) ===
            JSON.stringify(after.parameters) &&
        before.requested_result_count === after.requested_result_count;
    const formulaMatch =
        before.score?.formula_version === after.score?.formula_version;

    return {
        compatible: true,
        message:
            parameterMatch && formulaMatch
                ? 'Compatible stored snapshots selected.'
                : 'The pair can be inspected, but frozen parameters or score versions differ. Review the warnings before interpreting deltas.',
    };
}

export function HistoryOverview({
    history,
    timezone,
}: {
    history: HistoryIndexData;
    timezone: string;
}) {
    const [selected, setSelected] = useState<string[]>(
        history.selected_anchor ? [history.selected_anchor] : [],
    );
    const [repeatOpen, setRepeatOpen] = useState(false);
    const [repeating, setRepeating] = useState(false);
    const [filters, setFilters] = useState(history.filters);
    const selectedRuns = useMemo(
        () => history.runs.filter((run) => selected.includes(run.public_id)),
        [history.runs, selected],
    );
    const pair = matchingPair(selectedRuns);
    const selectedPair =
        selectedRuns.length === 2 && pair.compatible ? selectedRuns : null;

    function toggle(publicId: string) {
        setSelected((current) =>
            current.includes(publicId)
                ? current.filter((id) => id !== publicId)
                : current.length < 2
                  ? [...current, publicId]
                  : current,
        );
    }
    function submitFilters(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSelected([]);
        router.get(
            '/history',
            { ...filters, page: 1 },
            { preserveScroll: true, preserveState: false },
        );
    }
    function repeat() {
        if (!history.repeat_source || repeating) {
            return;
        }

        setRepeating(true);
        router.post(
            `/history/runs/${history.repeat_source.public_id}/repeat`,
            { confirmation: true, submission_token: crypto.randomUUID() },
            { preserveScroll: true, onFinish: () => setRepeating(false) },
        );
    }

    if (history.runs.length === 0 && history.pagination.total === 0) {
        return (
            <Card className="border-dashed py-10 text-center">
                <CardContent>
                    <History
                        className="mx-auto size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h2 className="mt-4 font-semibold">
                        No research history yet
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Start a market-specific search to create the first
                        immutable snapshot.
                    </p>
                    <Button asChild className="mt-5">
                        <Link href="/search">
                            <Search aria-hidden="true" />
                            Start a search
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Search history</CardTitle>
                    <CardDescription>
                        Filter immutable snapshots by their stored evidence.
                        Results are ordered newest first.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={submitFilters}
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-5"
                    >
                        <Input
                            aria-label="Search history"
                            placeholder="Search query"
                            value={filters.q}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    q: event.target.value,
                                })
                            }
                        />
                        <select
                            aria-label="Market"
                            className="h-10 rounded-md border bg-background px-3 text-sm"
                            value={filters.market ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    market: event.target.value || null,
                                })
                            }
                        >
                            <option value="">All markets</option>
                            {history.filter_options.markets.map((market) => (
                                <option key={market} value={market}>
                                    {market}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label="Status"
                            className="h-10 rounded-md border bg-background px-3 text-sm"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    status: (event.target.value ||
                                        null) as typeof filters.status,
                                })
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="completed">Completed</option>
                            <option value="queued">Queued</option>
                            <option value="searching">Searching</option>
                            <option value="enriching">Enriching</option>
                            <option value="scoring">Scoring</option>
                            <option value="failed">Failed</option>
                        </select>
                        <Input
                            aria-label="Minimum score"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="Minimum score"
                            value={filters.min_score ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    min_score:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                        />
                        <Input
                            aria-label="Minimum confidence"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="Minimum confidence"
                            value={filters.min_confidence ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    min_confidence:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                        />
                        <Input
                            aria-label="From date"
                            type="date"
                            value={filters.date_from ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    date_from: event.target.value || null,
                                })
                            }
                        />
                        <Input
                            aria-label="To date"
                            type="date"
                            value={filters.date_to ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    date_to: event.target.value || null,
                                })
                            }
                        />
                        <select
                            aria-label="Project"
                            className="h-10 rounded-md border bg-background px-3 text-sm"
                            value={filters.project ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    project: event.target.value || null,
                                })
                            }
                        >
                            <option value="">All projects</option>
                            {history.filter_options.projects.map((project) => (
                                <option
                                    key={project.public_id}
                                    value={project.public_id}
                                >
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label="Workspace"
                            className="h-10 rounded-md border bg-background px-3 text-sm"
                            value={filters.workspace ?? ''}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    workspace: event.target.value || null,
                                })
                            }
                        >
                            <option value="">All workspaces</option>
                            {history.filter_options.workspaces.map(
                                (workspace) => (
                                    <option
                                        key={workspace.public_id}
                                        value={workspace.public_id}
                                    >
                                        {workspace.name}
                                    </option>
                                ),
                            )}
                        </select>
                        <div className="flex gap-2">
                            <Button type="submit">Apply filters</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setFilters({
                                        ...history.filters,
                                        q: '',
                                        market: null,
                                        status: null,
                                        min_score: null,
                                        min_confidence: null,
                                        date_from: null,
                                        date_to: null,
                                        project: null,
                                        workspace: null,
                                    });
                                    router.get('/history');
                                }}
                            >
                                Clear
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <GitCompareArrows
                            className="size-5"
                            aria-hidden="true"
                        />
                        Direct snapshot comparison
                    </CardTitle>
                    <CardDescription>
                        Select exactly two completed rows. Stored values
                        describe observations, not causal results or
                        recommendations.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <Alert>
                        <History aria-hidden="true" />
                        <AlertTitle>
                            {pair.compatible
                                ? 'Pair ready'
                                : 'Selection needed'}
                        </AlertTitle>
                        <AlertDescription>{pair.message}</AlertDescription>
                    </Alert>
                    <Button
                        disabled={!selectedPair}
                        asChild={Boolean(selectedPair)}
                    >
                        {selectedPair ? (
                            <Link
                                href={`/history/compare/${selectedPair[0]!.public_id}/${selectedPair[1]!.public_id}`}
                            >
                                Compare selected snapshots
                            </Link>
                        ) : (
                            <span>Compare selected snapshots</span>
                        )}
                    </Button>
                    {history.repeat_source && (
                        <Button
                            variant="outline"
                            onClick={() => setRepeatOpen(true)}
                        >
                            Repeat with same parameters
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Card className="overflow-hidden">
                <CardHeader>
                    <CardTitle>Run timeline</CardTitle>
                    <CardDescription>
                        {history.pagination.total} owner-scoped snapshots. Times
                        use your account timezone.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Select</TableHead>
                                <TableHead>
                                    Snapshot / frozen parameters
                                </TableHead>
                                <TableHead>Market / status</TableHead>
                                <TableHead className="text-right">
                                    Score / confidence
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {history.runs.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No snapshots match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                history.runs.map((run) => (
                                    <TableRow key={run.public_id}>
                                        <TableCell className="pl-6">
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${run.query_text}`}
                                                checked={selected.includes(
                                                    run.public_id,
                                                )}
                                                disabled={
                                                    !selected.includes(
                                                        run.public_id,
                                                    ) && selected.length >= 2
                                                }
                                                onChange={() =>
                                                    toggle(run.public_id)
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="min-w-72">
                                            <Link
                                                href={`/research/runs/${run.public_id}`}
                                                className="font-medium hover:text-primary hover:underline"
                                            >
                                                {run.query_text}
                                            </Link>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatTimestamp(
                                                    run.completed_at ??
                                                        run.failed_at ??
                                                        run.created_at,
                                                    timezone,
                                                )}{' '}
                                                · Attempt {run.attempt_number}
                                            </p>
                                            <FrozenParameters run={run} />
                                        </TableCell>
                                        <TableCell className="space-y-2">
                                            <MarketBadge
                                                market={run.market_key}
                                            />
                                            <RunStatus
                                                state={
                                                    run.status ===
                                                        'completed' &&
                                                    run.collection_warnings
                                                        .length > 0
                                                        ? 'partial'
                                                        : run.status
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="min-w-44 text-right tabular-nums">
                                            {run.score ? (
                                                <>
                                                    <Badge>
                                                        Score{' '}
                                                        {run.score.overall_score.toFixed(
                                                            1,
                                                        )}
                                                    </Badge>
                                                    <Badge
                                                        className="ml-1"
                                                        variant="outline"
                                                    >
                                                        Confidence{' '}
                                                        {run.score.confidence_score.toFixed(
                                                            1,
                                                        )}
                                                    </Badge>
                                                </>
                                            ) : (
                                                'Not scored'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
                {history.pagination.last_page > 1 && (
                    <CardContent className="flex items-center justify-between border-t pt-5">
                        <span className="text-sm text-muted-foreground">
                            Page {history.pagination.current_page} of{' '}
                            {history.pagination.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={history.pagination.current_page === 1}
                                onClick={() =>
                                    router.get('/history', {
                                        ...history.filters,
                                        page:
                                            history.pagination.current_page - 1,
                                    })
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={
                                    history.pagination.current_page ===
                                    history.pagination.last_page
                                }
                                onClick={() =>
                                    router.get('/history', {
                                        ...history.filters,
                                        page:
                                            history.pagination.current_page + 1,
                                    })
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </CardContent>
                )}
            </Card>

            <Dialog open={repeatOpen} onOpenChange={setRepeatOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Repeat with the same frozen parameters?
                        </DialogTitle>
                        <DialogDescription>
                            This creates and queues a new run only after
                            confirmation. The completed snapshot remains
                            unchanged.
                        </DialogDescription>
                    </DialogHeader>
                    {history.repeat_source && (
                        <FrozenParameters run={history.repeat_source} />
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button onClick={repeat} disabled={repeating}>
                            {repeating
                                ? 'Creating repeat'
                                : 'Confirm and queue repeat'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
