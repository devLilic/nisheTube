import { Activity, BarChart3, History } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AnalyzerRun } from '@/types';

const number = (value: number | null, suffix = '') =>
    value === null
        ? 'Unavailable'
        : `${new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(value)}${suffix}`;

const signed = (value: number | null, suffix = '') =>
    value === null
        ? 'Unavailable'
        : `${value > 0 ? '+' : ''}${number(value, suffix)}`;

const timestamp = (value: string | null, timezone: string) =>
    value === null
        ? 'Unavailable'
        : new Intl.DateTimeFormat('en', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: timezone,
          }).format(new Date(value));

const label = (value: string | null) =>
    value === null
        ? 'Insufficient data'
        : value
              .replaceAll('_', ' ')
              .replace(/\b\w/g, (letter) => letter.toUpperCase());

function GrowthPlot({
    points,
}: {
    points: NonNullable<AnalyzerRun['growth_history']>['points'];
}) {
    const videoSeries = points.some((point) => point.view_count !== null);
    const values = points
        .map((point) =>
            videoSeries ? point.view_count : point.channel_view_count,
        )
        .filter((value): value is number => value !== null);

    if (points.length < 2 || values.length < 2) {
        return null;
    }

    const minimum = Math.min(...values);
    const maximum = Math.max(...values);
    const range = Math.max(1, maximum - minimum);
    const coordinates = points
        .map((point, index) => {
            const pointValue = videoSeries
                ? point.view_count
                : point.channel_view_count;

            if (pointValue === null) {
                return null;
            }

            const x =
                points.length === 1 ? 50 : (index / (points.length - 1)) * 100;
            const y = 92 - ((pointValue - minimum) / range) * 84;

            return `${x},${y}`;
        })
        .filter((point): point is string => point !== null)
        .join(' ');

    return (
        <div className="rounded-xl border bg-muted/20 p-4">
            <svg
                viewBox="0 0 100 100"
                className="h-40 w-full"
                aria-hidden="true"
            >
                <line
                    x1="0"
                    y1="92"
                    x2="100"
                    y2="92"
                    className="stroke-border"
                    strokeWidth="1"
                />
                <polyline
                    points={coordinates}
                    fill="none"
                    className="stroke-primary"
                    strokeWidth="2.5"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
            <p className="text-xs text-muted-foreground">
                {videoSeries ? 'Video' : 'Channel'} views across observed
                snapshots. Exact accessible values follow in the table.
            </p>
        </div>
    );
}

export function AnalyzerGrowthHistory({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const metrics = run.channel_metrics;
    const videoMetrics = run.metrics;
    const history = run.growth_history;
    const points = history?.points ?? [];

    if (!metrics || !history) {
        return run.is_active ? (
            <StatePanel
                title="Calculating channel behavior"
                description="Momentum, consistency, and observed growth appear after pinned cohort calculations finish."
            />
        ) : null;
    }

    const behaviorAvailable = metrics.behavior_version !== null;

    return (
        <section
            aria-labelledby="channel-behavior-heading"
            className="space-y-4"
        >
            <div className="flex items-center gap-2">
                <Activity className="size-5 text-primary" />
                <h2
                    id="channel-behavior-heading"
                    className="text-xl font-semibold"
                >
                    Channel Behavior
                </h2>
                <Badge variant="outline">Calculated Metrics</Badge>
            </div>

            {!behaviorAvailable && (
                <StatePanel
                    title="Channel behavior was not calculated"
                    description="This historical attempt predates channel-behavior-v1. Refresh to create a new immutable attempt with momentum and consistency evidence."
                />
            )}

            {behaviorAvailable && (
                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent momentum</CardTitle>
                            <CardDescription>
                                Median Lifetime Average Views/Day for the newest
                                block versus the preceding block.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-2xl font-semibold tabular-nums">
                                    {number(metrics.momentum_ratio, 'x')}
                                </span>
                                <Badge variant="outline">
                                    {label(metrics.momentum_class)}
                                </Badge>
                            </div>
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Recent block
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {number(
                                            metrics.momentum_recent_median_views_per_day,
                                        )}{' '}
                                        / day ({metrics.momentum_recent_count})
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Previous block
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {number(
                                            metrics.momentum_previous_median_views_per_day,
                                        )}{' '}
                                        / day ({metrics.momentum_previous_count}
                                        )
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Consistency</CardTitle>
                            <CardDescription>
                                Robust MAD/median dispersion across public
                                Lifetime Average Views/Day values.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-2xl font-semibold tabular-nums">
                                    {number(metrics.consistency_score, '/100')}
                                </span>
                                <Badge variant="outline">
                                    {label(metrics.consistency_class)}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {metrics.consistency_sample_count} valid videos;
                                minimum{' '}
                                {run.behavior_context
                                    ?.minimum_consistency_sample ?? 5}
                                .
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Duration / performance</CardTitle>
                            <CardDescription>
                                Observed Spearman correlation with Lifetime
                                Average Views/Day—not causation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-2xl font-semibold tabular-nums">
                                    {number(
                                        metrics.duration_performance_correlation,
                                    )}
                                </span>
                                <Badge variant="outline">
                                    {label(metrics.duration_performance_class)}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {metrics.duration_performance_sample_count}{' '}
                                comparable videos; minimum{' '}
                                {run.behavior_context
                                    ?.minimum_correlation_sample ?? 5}
                                .
                            </p>
                            <dl className="grid grid-cols-2 gap-2 text-xs">
                                {Object.entries(
                                    metrics.duration_performance_buckets,
                                ).map(([bucket, summary]) => (
                                    <div
                                        key={bucket}
                                        className="rounded-lg border p-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {bucket.replaceAll('_', ' ')}
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {number(
                                                summary.median_views_per_day,
                                            )}{' '}
                                            / day ({summary.count})
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            )}

            {behaviorAvailable && (
                <p className="text-xs text-muted-foreground">
                    {metrics.behavior_version ?? run.behavior_context?.version}{' '}
                    · Momentum uses{' '}
                    {run.behavior_context?.momentum_block_size ?? 5} +{' '}
                    {run.behavior_context?.momentum_block_size ?? 5} fixed
                    playlist positions. Declining is below{' '}
                    {run.behavior_context?.declining_below ?? 0.8}x; growing is
                    above {run.behavior_context?.growing_above ?? 1.2}x.
                </p>
            )}

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <History className="size-5 text-primary" />
                        <CardTitle>Growth History</CardTitle>
                    </div>
                    <CardDescription>
                        Only snapshots NisheTube observed for your account are
                        shown. History starts at first seen and remains subject
                        to the six-month snapshot retention boundary.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border p-3">
                            <p className="text-xs text-muted-foreground">
                                First seen
                            </p>
                            <p className="mt-1 font-medium">
                                {timestamp(history.first_seen_at, timezone)}
                            </p>
                        </div>
                        {videoMetrics ? (
                            <>
                                <div className="rounded-xl border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Observed Recent Views/Day
                                    </p>
                                    <p className="mt-1 font-medium tabular-nums">
                                        {number(
                                            videoMetrics.observed_recent_views_per_day,
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-xl border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Lifetime Average Views/Day
                                    </p>
                                    <p className="mt-1 font-medium tabular-nums">
                                        {number(
                                            videoMetrics.lifetime_views_per_day,
                                        )}
                                    </p>
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="rounded-xl border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Observed channel view delta
                                    </p>
                                    <p className="mt-1 font-medium tabular-nums">
                                        {signed(metrics.observed_view_delta)}
                                    </p>
                                </div>
                                <div className="rounded-xl border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Observed subscriber delta
                                    </p>
                                    <p className="mt-1 font-medium tabular-nums">
                                        {signed(
                                            metrics.observed_subscriber_delta,
                                        )}
                                    </p>
                                </div>
                            </>
                        )}
                    </div>

                    {points.length < 2 ? (
                        <StatePanel
                            title="One observed snapshot"
                            description="Refresh later to calculate deltas. NisheTube does not backfill activity before the first observed snapshot."
                        />
                    ) : (
                        <>
                            <GrowthPlot points={points} />
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Observed</TableHead>
                                            <TableHead className="text-right">
                                                Video views
                                            </TableHead>
                                            <TableHead className="text-right">
                                                View delta
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Observed recent views/day
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Likes / comments delta
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Channel views
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Subscribers
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {points.map((point) => (
                                            <TableRow
                                                key={point.attempt_public_id}
                                            >
                                                <TableCell className="whitespace-nowrap">
                                                    {timestamp(
                                                        point.observed_at,
                                                        timezone,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {number(point.view_count)}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {signed(point.view_delta)}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {number(
                                                        point.observed_recent_views_per_day,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {signed(point.like_delta)} /{' '}
                                                    {signed(
                                                        point.comment_delta,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {number(
                                                        point.channel_view_count,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {number(
                                                        point.channel_subscriber_count,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </>
                    )}
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <BarChart3 className="size-4" /> Retention cutoff
                        currently{' '}
                        {timestamp(history.retention_cutoff_at, timezone)}.
                        Cached attempts reusing the same snapshot do not create
                        invented growth points.
                    </p>
                </CardContent>
            </Card>
        </section>
    );
}
