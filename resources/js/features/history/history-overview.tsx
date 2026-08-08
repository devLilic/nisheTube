import { Link, router } from '@inertiajs/react';
import { ArrowRight, GitCompareArrows, History, Search } from 'lucide-react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    if (!value) {
        return 'Not completed';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function scoreLabel(run: HistoryRun) {
    if (!run.score) {
        return <span className="text-muted-foreground">Not scored</span>;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-1.5 tabular-nums">
            <Badge variant="secondary">
                Score {run.score.overall_score.toFixed(1)}
            </Badge>
            <Badge variant="outline">
                Confidence {run.score.confidence_score.toFixed(1)}
            </Badge>
        </div>
    );
}

function parameterSummary(run: HistoryRun) {
    const entries = Object.entries(run.parameters).filter(
        ([, value]) => value !== null && value !== '',
    );

    if (entries.length === 0) {
        return 'Default collection parameters';
    }

    return entries
        .slice(0, 3)
        .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${String(value)}`)
        .join(' · ');
}

export function HistoryOverview({
    history,
    timezone,
}: {
    history: HistoryIndexData;
    timezone: string;
}) {
    const [candidateSelection, setCandidateSelection] = useState({
        anchor: history.selected_anchor,
        id: '',
    });
    const completedRuns = useMemo(
        () => history.runs.filter((run) => run.can_compare),
        [history.runs],
    );
    const anchor = history.runs.find(
        (run) => run.public_id === history.selected_anchor,
    );
    const candidateId =
        candidateSelection.anchor === history.selected_anchor
            ? candidateSelection.id
            : '';

    if (history.runs.length === 0) {
        return (
            <Card className="border-dashed py-10 text-center">
                <CardContent className="flex flex-col items-center">
                    <span className="rounded-full bg-muted p-3 text-muted-foreground">
                        <History className="size-6" aria-hidden="true" />
                    </span>
                    <h2 className="mt-4 font-semibold">
                        No research history yet
                    </h2>
                    <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
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

    const compareHref =
        history.selected_anchor && candidateId
            ? `/history/compare/${history.selected_anchor}/${candidateId}`
            : null;

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <GitCompareArrows
                            className="size-5 text-primary"
                            aria-hidden="true"
                        />
                        Compare snapshots
                    </CardTitle>
                    <CardDescription>
                        Choose a completed snapshot, then select a useful pair
                        with the same normalized query, market, and collection
                        kind.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                    <label className="grid gap-1.5 text-sm font-medium">
                        Baseline snapshot
                        <Select
                            value={history.selected_anchor ?? ''}
                            onValueChange={(value) =>
                                router.get(
                                    '/history',
                                    { anchor: value },
                                    {
                                        preserveScroll: true,
                                        preserveState: false,
                                    },
                                )
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choose a completed run" />
                            </SelectTrigger>
                            <SelectContent align="start">
                                {completedRuns.map((run) => (
                                    <SelectItem
                                        key={run.public_id}
                                        value={run.public_id}
                                    >
                                        {run.query_text} ·{' '}
                                        {formatTimestamp(
                                            run.completed_at,
                                            timezone,
                                        )}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium">
                        Comparison snapshot
                        <Select
                            value={candidateId}
                            onValueChange={(value) =>
                                setCandidateSelection({
                                    anchor: history.selected_anchor,
                                    id: value,
                                })
                            }
                            disabled={
                                !history.selected_anchor ||
                                history.candidates.length === 0
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choose a compatible run" />
                            </SelectTrigger>
                            <SelectContent align="start">
                                {history.candidates.map((candidate) => (
                                    <SelectItem
                                        key={candidate.public_id}
                                        value={candidate.public_id}
                                    >
                                        {formatTimestamp(
                                            candidate.completed_at,
                                            timezone,
                                        )}{' '}
                                        ·{' '}
                                        {candidate.overall_score === null
                                            ? 'Not scored'
                                            : `Score ${candidate.overall_score.toFixed(1)}`}
                                        {candidate.warning_codes.length > 0
                                            ? ' · Review warnings'
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    <Button
                        asChild={compareHref !== null}
                        disabled={compareHref === null}
                    >
                        {compareHref ? (
                            <Link href={compareHref}>
                                Compare
                                <ArrowRight aria-hidden="true" />
                            </Link>
                        ) : (
                            <span>
                                Compare
                                <ArrowRight aria-hidden="true" />
                            </span>
                        )}
                    </Button>
                </CardContent>
                {history.selected_anchor && history.candidates.length === 0 && (
                    <CardContent className="pt-0">
                        <Alert>
                            <History aria-hidden="true" />
                            <AlertTitle>
                                Not enough compatible history
                            </AlertTitle>
                            <AlertDescription>
                                {anchor?.query_text ?? 'This query'} needs
                                another completed run with the same market and
                                collection kind before it can be compared.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                )}
                {completedRuns.length < 2 && !history.selected_anchor && (
                    <CardContent className="pt-0">
                        <Alert>
                            <History aria-hidden="true" />
                            <AlertTitle>
                                Complete another matching run
                            </AlertTitle>
                            <AlertDescription>
                                The timeline is available now. Comparison
                                unlocks when two compatible snapshots exist.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                )}
            </Card>

            <Card className="overflow-hidden">
                <CardHeader>
                    <CardTitle>Run timeline</CardTitle>
                    <CardDescription>
                        The latest {history.runs.length} owner-scoped search and
                        discovery-validation runs. Times use your account
                        timezone.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Snapshot</TableHead>
                                <TableHead>Market / status</TableHead>
                                <TableHead>Parameters</TableHead>
                                <TableHead className="text-right">
                                    Score / confidence
                                </TableHead>
                                <TableHead className="pr-6 text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {history.runs.map((run) => (
                                <TableRow key={run.public_id}>
                                    <TableCell className="min-w-56 pl-6">
                                        <div className="border-l-2 border-primary/35 pl-3">
                                            <Link
                                                href={`/research/runs/${run.public_id}`}
                                                className="line-clamp-2 font-medium hover:text-primary hover:underline"
                                                title={run.query_text}
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
                                        </div>
                                    </TableCell>
                                    <TableCell className="min-w-44 space-y-2">
                                        <MarketBadge market={run.market_key} />
                                        <RunStatus
                                            state={
                                                run.status === 'completed' &&
                                                run.collection_warnings.length >
                                                    0
                                                    ? 'partial'
                                                    : run.status
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="max-w-72 text-xs leading-5 text-muted-foreground">
                                        <p className="capitalize">
                                            {run.kind.replaceAll('_', ' ')}
                                        </p>
                                        <p
                                            className="line-clamp-2"
                                            title={parameterSummary(run)}
                                        >
                                            {parameterSummary(run)}
                                        </p>
                                        <p>
                                            {run.collected_result_count} /{' '}
                                            {run.requested_result_count} results
                                        </p>
                                    </TableCell>
                                    <TableCell className="min-w-48 text-right">
                                        {scoreLabel(run)}
                                    </TableCell>
                                    <TableCell className="pr-6 text-right">
                                        {run.can_compare ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.get(
                                                        '/history',
                                                        {
                                                            anchor: run.public_id,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Select
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                            >
                                                <Link
                                                    href={`/research/runs/${run.public_id}`}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {history.truncated && (
                <Alert>
                    <History aria-hidden="true" />
                    <AlertTitle>Showing the latest 100 runs</AlertTitle>
                    <AlertDescription>
                        Older immutable history remains stored and will be
                        included in later pagination work.
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
