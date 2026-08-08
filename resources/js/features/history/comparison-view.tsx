import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CheckCircle2,
    GitCompareArrows,
    Minus,
} from 'lucide-react';
import { MarketBadge } from '@/components/market-badge';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type {
    HistoryChannelEntity,
    HistoryComparison,
    HistoryComponentKey,
    HistoryDelta,
    HistoryPair,
    HistoryVideoEntity,
} from '@/types';

const componentLabels: Record<HistoryComponentKey, string> = {
    demand_momentum: 'Demand momentum',
    competition_opportunity: 'Competition opportunity',
    audience_reachability: 'Audience reachability',
    content_freshness_gap: 'Content freshness gap',
    creator_viability: 'Creator viability',
};

const metricLabels: Record<string, { label: string; digits?: number }> = {
    median_views: { label: 'Median views', digits: 0 },
    median_views_per_day: { label: 'Median views / day', digits: 1 },
    median_subscribers: { label: 'Median subscribers', digits: 0 },
    median_reach_ratio: { label: 'Median reach ratio', digits: 2 },
    median_engagement_rate: { label: 'Median engagement', digits: 2 },
    video_count: { label: 'Videos sampled', digits: 0 },
    channel_count: { label: 'Channels represented', digits: 0 },
};

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function formatValue(value: number | null, digits = 1) {
    return value === null
        ? 'Not available'
        : value.toLocaleString('en-US', { maximumFractionDigits: digits });
}

function formatUnknown(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return 'Not set';
    }

    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : JSON.stringify(value);
}

function DeltaIcon({ delta }: { delta: number | null }) {
    if (delta === null || delta === 0) {
        return <Minus aria-hidden="true" />;
    }

    return delta > 0 ? (
        <ArrowUpRight aria-hidden="true" />
    ) : (
        <ArrowDownRight aria-hidden="true" />
    );
}

function DeltaCard({
    label,
    delta,
    digits = 1,
}: {
    label: string;
    delta: HistoryDelta;
    digits?: number;
}) {
    return (
        <Card className="gap-4 py-5">
            <CardContent className="px-5">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <div className="mt-3 flex items-end justify-between gap-3">
                    <div>
                        <p className="text-2xl font-semibold tabular-nums">
                            {formatValue(delta.after, digits)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Before {formatValue(delta.before, digits)}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        className={cn(
                            'gap-1 tabular-nums',
                            delta.delta !== null &&
                                delta.delta > 0 &&
                                'border-success/35 bg-success/10 text-success-foreground',
                            delta.delta !== null &&
                                delta.delta < 0 &&
                                'border-destructive/30 bg-destructive/8 text-destructive',
                        )}
                    >
                        <DeltaIcon delta={delta.delta} />
                        {delta.delta === null
                            ? 'Unavailable'
                            : `${delta.delta > 0 ? '+' : ''}${formatValue(delta.delta, digits)}`}
                    </Badge>
                </div>
            </CardContent>
        </Card>
    );
}

function ComponentChart({ comparison }: { comparison: HistoryComparison }) {
    const components = Object.entries(comparison.score_deltas.components) as [
        HistoryComponentKey,
        HistoryDelta,
    ][];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Component score change</CardTitle>
                <CardDescription>
                    Stored score components on a 0–100 scale. Exact values are
                    repeated in the table alternative.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {!comparison.compatibility.score_comparable ? (
                    <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Component deltas are unavailable because the stored
                        formula versions differ or a score is missing.
                    </div>
                ) : (
                    <div
                        className="space-y-5"
                        aria-label="Component score comparison chart"
                    >
                        {components.map(([key, delta]) => (
                            <div key={key}>
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="font-medium">
                                        {componentLabels[key]}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatValue(delta.before)} →{' '}
                                        {formatValue(delta.after)}
                                    </span>
                                </div>
                                <div className="mt-2 grid gap-1.5">
                                    <div
                                        className="h-2.5 overflow-hidden rounded-full bg-muted"
                                        title={`Before ${formatValue(delta.before)}`}
                                    >
                                        <div
                                            className="h-full rounded-full bg-muted-foreground/45"
                                            style={{
                                                width: `${delta.before ?? 0}%`,
                                            }}
                                        />
                                    </div>
                                    <div
                                        className="h-2.5 overflow-hidden rounded-full bg-muted"
                                        title={`After ${formatValue(delta.after)}`}
                                    >
                                        <div
                                            className="h-full rounded-full bg-primary"
                                            style={{
                                                width: `${delta.after ?? 0}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                <details className="group mt-5 rounded-lg border px-4 py-3">
                    <summary className="cursor-pointer text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        View exact component values
                    </summary>
                    <div className="mt-3 border-t pt-2">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Component</TableHead>
                                    <TableHead className="text-right">
                                        Before
                                    </TableHead>
                                    <TableHead className="text-right">
                                        After
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Delta
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {components.map(([key, delta]) => (
                                    <TableRow key={key}>
                                        <TableCell>
                                            {componentLabels[key]}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(delta.before)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(delta.after)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatValue(delta.delta)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </details>
            </CardContent>
        </Card>
    );
}

function VideoList({
    title,
    items,
}: {
    title: string;
    items: HistoryVideoEntity[];
}) {
    return (
        <div className="rounded-lg border p-4">
            <h3 className="text-sm font-semibold">
                {title}{' '}
                <span className="text-muted-foreground">({items.length})</span>
            </h3>
            {items.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    No videos in this group.
                </p>
            ) : (
                <ul className="mt-3 space-y-3">
                    {items.slice(0, 8).map((video) => (
                        <li
                            key={video.provider_video_id}
                            className="min-w-0 text-sm"
                        >
                            <a
                                href={`https://www.youtube.com/watch?v=${encodeURIComponent(video.provider_video_id)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="line-clamp-2 font-medium hover:text-primary hover:underline"
                                title={video.title}
                            >
                                {video.title}
                            </a>
                            <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                                Rank {video.result_rank} ·{' '}
                                {formatValue(video.views_per_day)} views/day
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function ChannelList({
    title,
    items,
}: {
    title: string;
    items: HistoryChannelEntity[];
}) {
    return (
        <div className="rounded-lg border p-4">
            <h3 className="text-sm font-semibold">
                {title}{' '}
                <span className="text-muted-foreground">({items.length})</span>
            </h3>
            {items.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    No channels in this group.
                </p>
            ) : (
                <ul className="mt-3 space-y-3">
                    {items.slice(0, 8).map((channel) => (
                        <li
                            key={channel.provider_channel_id}
                            className="min-w-0 text-sm"
                        >
                            <a
                                href={`https://www.youtube.com/channel/${encodeURIComponent(channel.provider_channel_id)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="line-clamp-2 font-medium hover:text-primary hover:underline"
                                title={channel.title}
                            >
                                {channel.title}
                            </a>
                            <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                                {channel.subscriber_count_hidden
                                    ? 'Subscribers hidden'
                                    : `${formatValue(channel.subscriber_count, 0)} subscribers`}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function ComparisonView({
    pair,
    comparison,
    timezone,
}: {
    pair: HistoryPair;
    comparison: HistoryComparison;
    timezone: string;
}) {
    const keyMetrics = [
        'median_views',
        'median_views_per_day',
        'median_subscribers',
        'channel_count',
    ];

    return (
        <div className="space-y-6">
            <Card className="overflow-hidden">
                <CardHeader>
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <GitCompareArrows
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                {pair.before.query_text}
                            </CardTitle>
                            <CardDescription className="mt-2">
                                A stored-snapshot comparison. Changes describe
                                observed returned videos and channels, not
                                YouTube search volume.
                            </CardDescription>
                        </div>
                        <MarketBadge market={pair.before.market_key} />
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-center">
                    <div className="rounded-lg border bg-muted/20 p-4">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Before
                        </p>
                        <p className="mt-2 font-medium">
                            {formatTimestamp(
                                pair.before.completed_at,
                                timezone,
                            )}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {pair.before.collected_result_count} /{' '}
                            {pair.before.requested_result_count} results ·{' '}
                            {pair.before.score?.formula_version ?? 'No score'}
                        </p>
                    </div>
                    <ArrowRight
                        className="mx-auto size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div className="rounded-lg border border-primary/25 bg-primary/5 p-4">
                        <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                            After
                        </p>
                        <p className="mt-2 font-medium">
                            {formatTimestamp(pair.after.completed_at, timezone)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {pair.after.collected_result_count} /{' '}
                            {pair.after.requested_result_count} results ·{' '}
                            {pair.after.score?.formula_version ?? 'No score'}
                        </p>
                    </div>
                </CardContent>
            </Card>

            {comparison.compatibility.warnings.map((warning) => (
                <Alert
                    key={warning.code}
                    className="border-warning/40 bg-warning/10"
                >
                    <AlertTriangle aria-hidden="true" />
                    <AlertTitle>{warning.code.replaceAll('_', ' ')}</AlertTitle>
                    <AlertDescription>{warning.message}</AlertDescription>
                </Alert>
            ))}

            {comparison.compatibility.warnings.length === 0 && (
                <Alert className="border-success/35 bg-success/8">
                    <CheckCircle2 aria-hidden="true" />
                    <AlertTitle>Comparable snapshots</AlertTitle>
                    <AlertDescription>
                        Query, market, collection parameters, and score formula
                        align for direct comparison.
                    </AlertDescription>
                </Alert>
            )}

            <section
                aria-label="Key comparison deltas"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <DeltaCard
                    label="Opportunity score"
                    delta={comparison.score_deltas.overall_score}
                />
                <DeltaCard
                    label="Confidence"
                    delta={comparison.score_deltas.confidence_score}
                />
                {keyMetrics.slice(0, 2).map((key) => (
                    <DeltaCard
                        key={key}
                        label={metricLabels[key].label}
                        delta={comparison.metric_deltas[key]}
                        digits={metricLabels[key].digits}
                    />
                ))}
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <ComponentChart comparison={comparison} />
                <Card>
                    <CardHeader>
                        <CardTitle>Collection compatibility</CardTitle>
                        <CardDescription>
                            Frozen parameter differences that can influence the
                            observed sample.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {comparison.compatibility.parameter_changes.length ===
                        0 ? (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Collection parameters match.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Parameter</TableHead>
                                        <TableHead>Before</TableHead>
                                        <TableHead>After</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {comparison.compatibility.parameter_changes.map(
                                        (change) => (
                                            <TableRow key={change.field}>
                                                <TableCell className="font-medium capitalize">
                                                    {change.field.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs break-all">
                                                    {formatUnknown(
                                                        change.before,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs break-all">
                                                    {formatUnknown(
                                                        change.after,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Metric deltas</CardTitle>
                    <CardDescription>
                        Robust medians preserve missing values instead of
                        treating them as zero.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {Object.entries(metricLabels).map(([key, meta]) => (
                        <DeltaCard
                            key={key}
                            label={meta.label}
                            delta={comparison.metric_deltas[key]}
                            digits={meta.digits}
                        />
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Video changes</CardTitle>
                    <CardDescription>
                        {comparison.videos.before_count} before,{' '}
                        {comparison.videos.after_count} after, and{' '}
                        {comparison.videos.retained.length} retained.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-2">
                    <VideoList
                        title="New videos"
                        items={comparison.videos.new}
                    />
                    <VideoList
                        title="Lost videos"
                        items={comparison.videos.lost}
                    />
                    <VideoList
                        title="Leading before"
                        items={comparison.videos.leading_before}
                    />
                    <VideoList
                        title="Leading after"
                        items={comparison.videos.leading_after}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Channel composition</CardTitle>
                    <CardDescription>
                        {comparison.channels.before_count} before,{' '}
                        {comparison.channels.after_count} after, and{' '}
                        {comparison.channels.retained.length} retained.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-2">
                    <ChannelList
                        title="New channels"
                        items={comparison.channels.new}
                    />
                    <ChannelList
                        title="Lost channels"
                        items={comparison.channels.lost}
                    />
                    <ChannelList
                        title="Leading before"
                        items={comparison.channels.leading_before}
                    />
                    <ChannelList
                        title="Leading after"
                        items={comparison.channels.leading_after}
                    />
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button variant="outline" asChild>
                    <Link href={`/history?anchor=${pair.after.public_id}`}>
                        Compare another snapshot
                    </Link>
                </Button>
            </div>
        </div>
    );
}
