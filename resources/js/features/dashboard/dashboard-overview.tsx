import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Bookmark,
    CalendarClock,
    CheckCircle2,
    Clock3,
    FolderKanban,
    Gauge,
    Search,
    Sparkles,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { MarketBadge } from '@/components/market-badge';
import { QuotaMeter } from '@/components/quota-meter';
import { RunStatus } from '@/components/run-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ScoreTrend } from '@/features/dashboard/score-trend';
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';
import type {
    DashboardData,
    DashboardOpportunity,
    DashboardRecentRun,
} from '@/types';

const activeStates = new Set([
    'draft',
    'queued',
    'searching',
    'enriching',
    'scoring',
]);

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function scoreLabel(score: number) {
    if (score >= 80) {
        return 'Strong opportunity';
    }

    if (score >= 65) {
        return 'Promising';
    }

    if (score >= 50) {
        return 'Mixed';
    }

    if (score >= 35) {
        return 'Competitive / uncertain';
    }

    return 'Weak observed opportunity';
}

function confidenceLabel(score: number) {
    if (score >= 80) {
        return 'High confidence';
    }

    if (score >= 60) {
        return 'Moderate confidence';
    }

    if (score >= 40) {
        return 'Limited confidence';
    }

    return 'Exploratory only';
}

function SummaryCard({
    label,
    value,
    detail,
    icon: Icon,
    footer,
}: {
    label: string;
    value: ReactNode;
    detail: string;
    icon: LucideIcon;
    footer?: ReactNode;
}) {
    return (
        <Card className="min-w-0 gap-4 overflow-hidden border-border/70 py-5 shadow-sm shadow-primary/5">
            <CardContent className="px-5">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-muted-foreground">
                            {label}
                        </p>
                        <div className="mt-2 min-h-9 text-2xl font-semibold tracking-tight tabular-nums">
                            {value}
                        </div>
                    </div>
                    <span className="shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                        <Icon className="size-4" aria-hidden="true" />
                    </span>
                </div>
                <p className="mt-3 text-xs leading-5 text-muted-foreground">
                    {detail}
                </p>
                {footer && <div className="mt-3">{footer}</div>}
            </CardContent>
        </Card>
    );
}

function RecentRunRow({
    run,
    timezone,
}: {
    run: DashboardRecentRun;
    timezone: string;
}) {
    const isActive = activeStates.has(run.status);
    const statusState =
        run.has_partial_data && run.status === 'completed'
            ? 'partial'
            : run.status;

    return (
        <TableRow>
            <TableCell className="min-w-64">
                <Link
                    href={show(run.public_id)}
                    className="group inline-flex max-w-full items-start gap-2 font-medium hover:text-primary"
                >
                    <span
                        className="line-clamp-2 break-words group-hover:underline"
                        title={run.query_text}
                    >
                        {run.query_text}
                    </span>
                    <ArrowRight className="mt-0.5 size-4 shrink-0 transition-transform group-hover:translate-x-0.5" />
                </Link>
                <p className="mt-1 text-xs text-muted-foreground">
                    {formatTimestamp(run.created_at, timezone)}
                </p>
                {run.status === 'failed' && run.error_message && (
                    <p className="mt-2 max-w-md text-xs leading-5 text-destructive">
                        {run.error_message} Open the saved run to review retry
                        guidance.
                    </p>
                )}
            </TableCell>
            <TableCell>
                <MarketBadge market={run.market_key} />
            </TableCell>
            <TableCell className="min-w-40">
                <RunStatus state={statusState} />
                {isActive && (
                    <div className="mt-2">
                        <div
                            className="h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-label={`${run.query_text} progress`}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={run.progress_percent}
                        >
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{ width: `${run.progress_percent}%` }}
                            />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                            {run.progress_percent}% ·{' '}
                            {run.collected_result_count} collected
                        </p>
                    </div>
                )}
            </TableCell>
            <TableCell className="min-w-40">
                {run.score ? (
                    <div className="space-y-2">
                        <p className="font-semibold tabular-nums">
                            {run.score.overall_score.toFixed(1)}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                / 100
                            </span>
                        </p>
                        <ConfidenceBadge
                            score={run.score.confidence_score}
                            label={confidenceLabel(run.score.confidence_score)}
                        />
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        {isActive ? 'Pending' : 'Unavailable'}
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}

function OpportunityCard({
    opportunity,
    timezone,
}: {
    opportunity: DashboardOpportunity;
    timezone: string;
}) {
    return (
        <Link
            href={show(opportunity.public_id)}
            className="group block rounded-xl border p-4 transition-colors hover:border-primary/40 hover:bg-primary/3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p
                        className="line-clamp-2 text-sm font-semibold group-hover:text-primary"
                        title={opportunity.query_text}
                    >
                        {opportunity.query_text}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <MarketBadge market={opportunity.market_key} />
                        <Badge variant="outline">
                            {opportunity.sample_size} videos
                        </Badge>
                    </div>
                </div>
                <div className="shrink-0 text-right">
                    <p className="text-2xl font-semibold tracking-tight tabular-nums">
                        {opportunity.overall_score.toFixed(1)}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        out of 100
                    </p>
                </div>
            </div>
            <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full rounded-full bg-primary"
                    style={{
                        width: `${Math.min(100, Math.max(0, opportunity.overall_score))}%`,
                    }}
                />
            </div>
            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <ConfidenceBadge
                    score={opportunity.confidence_score}
                    label={confidenceLabel(opportunity.confidence_score)}
                />
                <span className="text-xs text-muted-foreground">
                    {formatTimestamp(opportunity.calculated_at, timezone)}
                </span>
            </div>
        </Link>
    );
}

export function DashboardOverview({
    dashboard,
    timezone,
}: {
    dashboard: DashboardData;
    timezone: string;
}) {
    const searchQuota = dashboard.quota.buckets.find(
        (bucket) =>
            bucket.bucket === 'search' || bucket.bucket === 'search.list',
    );
    const best = dashboard.best_opportunity;

    return (
        <>
            <section
                className="grid gap-4 md:grid-cols-2 2xl:grid-cols-4"
                aria-label="Dashboard summary"
            >
                <SummaryCard
                    label="Research runs this month"
                    value={dashboard.counts.research_runs_this_month.toLocaleString()}
                    detail={`${dashboard.counts.active_runs.toLocaleString()} active now · ${dashboard.counts.failed_runs_this_month.toLocaleString()} failed this month`}
                    icon={BarChart3}
                />
                <SummaryCard
                    label="Best recent opportunity"
                    value={best ? best.overall_score.toFixed(1) : '—'}
                    detail={
                        best
                            ? `${scoreLabel(best.overall_score)} · last ${dashboard.opportunity_window_days} days`
                            : `No scored runs in the last ${dashboard.opportunity_window_days} days`
                    }
                    icon={Gauge}
                    footer={
                        best ? (
                            <ConfidenceBadge
                                score={best.confidence_score}
                                label={confidenceLabel(best.confidence_score)}
                            />
                        ) : undefined
                    }
                />
                <SummaryCard
                    label="Saved research"
                    value={
                        dashboard.availability.saved_items
                            ? (
                                  dashboard.counts.saved_items ?? 0
                              ).toLocaleString()
                            : dashboard.counts.saved_projects.toLocaleString()
                    }
                    detail={
                        dashboard.availability.saved_items
                            ? 'Saved niches and favorites'
                            : `Projects saved · favorites become available with Library`
                    }
                    icon={
                        dashboard.availability.saved_items
                            ? Bookmark
                            : FolderKanban
                    }
                    footer={
                        !dashboard.availability.saved_items ? (
                            <Badge variant="outline">Library coming soon</Badge>
                        ) : undefined
                    }
                />
                <SummaryCard
                    label="YouTube search quota"
                    value={
                        searchQuota
                            ? searchQuota.remaining.toLocaleString()
                            : '—'
                    }
                    detail={
                        searchQuota
                            ? `${searchQuota.allowance.toLocaleString()} daily calls · NisheTube estimate`
                            : 'Estimate unavailable · review integration settings'
                    }
                    icon={Search}
                    footer={
                        searchQuota?.exhausted ? (
                            <Badge variant="destructive">Reset required</Badge>
                        ) : searchQuota ? (
                            <Badge variant="outline">
                                {searchQuota.used.toLocaleString()} used today
                            </Badge>
                        ) : undefined
                    }
                />
            </section>

            <Alert
                className={
                    dashboard.cleanup.status === 'due'
                        ? 'border-warning/45 bg-warning/10 text-warning-foreground'
                        : 'border-success/35 bg-success/8 text-success-foreground'
                }
            >
                {dashboard.cleanup.status === 'due' ? (
                    <AlertTriangle aria-hidden="true" />
                ) : (
                    <CheckCircle2 aria-hidden="true" />
                )}
                <AlertTitle>
                    {dashboard.cleanup.status === 'due'
                        ? `${dashboard.cleanup.candidate_run_count.toLocaleString()} old snapshot ${dashboard.cleanup.candidate_run_count === 1 ? 'run needs' : 'runs need'} review`
                        : 'Snapshot cleanup is current'}
                </AlertTitle>
                <AlertDescription className="text-current/80">
                    {dashboard.cleanup.status === 'due'
                        ? `Runs older than ${formatTimestamp(dashboard.cleanup.cutoff_at, timezone)} are candidates for the future retention preview. Nothing is deleted from this dashboard.`
                        : `No completed or failed runs are older than the six-month review cutoff of ${formatTimestamp(dashboard.cleanup.cutoff_at, timezone)}.`}
                </AlertDescription>
            </Alert>

            <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(20rem,0.85fr)] xl:items-start">
                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>Recent research runs</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Live status, saved partial results,
                                    failures, and scored snapshots.
                                </p>
                            </div>
                            <Clock3
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="px-0">
                        {dashboard.recent_runs.length === 0 ? (
                            <div className="mx-6 flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                                <Search
                                    className="size-7 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="mt-3 text-sm font-medium">
                                    No research runs yet
                                </p>
                                <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                                    Start a market-specific search to create
                                    your first durable observed-demand snapshot.
                                </p>
                                <Link
                                    href={create()}
                                    className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                                >
                                    Start a search
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Research
                                        </TableHead>
                                        <TableHead>Market</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="pr-6">
                                            Opportunity
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {dashboard.recent_runs.map((run) => (
                                        <RecentRunRow
                                            key={run.public_id}
                                            run={run}
                                            timezone={timezone}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card className="min-w-0">
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>High-opportunity research</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Strongest scored runs from the last{' '}
                                    {dashboard.opportunity_window_days} days.
                                </p>
                            </div>
                            <Sparkles
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {dashboard.top_opportunities.length === 0 ? (
                            <div className="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                                <Sparkles
                                    className="size-7 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="mt-3 text-sm font-medium">
                                    No scored opportunities yet
                                </p>
                                <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                                    Completed scores appear here with their
                                    confidence and sample timestamp.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {dashboard.top_opportunities.map(
                                    (opportunity) => (
                                        <OpportunityCard
                                            key={opportunity.public_id}
                                            opportunity={opportunity}
                                            timezone={timezone}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(20rem,0.85fr)] xl:items-start">
                <ScoreTrend
                    points={dashboard.score_trend}
                    timezone={timezone}
                />

                <Card className="min-w-0">
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>YouTube API today</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Project-wide local estimate. Google Cloud
                                    Console remains authoritative.
                                </p>
                            </div>
                            <CalendarClock
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {dashboard.quota.buckets.length === 0 ? (
                            <Alert variant="destructive">
                                <AlertTriangle aria-hidden="true" />
                                <AlertTitle>
                                    Quota estimate unavailable
                                </AlertTitle>
                                <AlertDescription>
                                    Review the local YouTube integration before
                                    starting another run.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            dashboard.quota.buckets.map((bucket) => (
                                <QuotaMeter
                                    key={bucket.bucket}
                                    summary={bucket}
                                />
                            ))
                        )}
                        <p className="border-t pt-4 text-xs leading-5 text-muted-foreground">
                            Resets{' '}
                            {formatTimestamp(
                                dashboard.quota.reset_at,
                                timezone,
                            )}
                            . Requests from other software using the same Google
                            project are not included.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
