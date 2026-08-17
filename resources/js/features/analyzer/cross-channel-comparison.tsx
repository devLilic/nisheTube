import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ExternalLink } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { PartialDataBanner } from '@/components/partial-data-banner';
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
import { formatDate, formatNumber } from '@/lib/formatters';
import type { AnalyzerComparisonValue, CrossChannelComparison } from '@/types';

const value = (number: number | null, suffix = '') =>
    number === null ? 'Not available' : `${formatNumber(number)}${suffix}`;

function CompatibilityBadge({ compatible }: { compatible: boolean }) {
    return compatible ? (
        <Badge variant="outline" className="gap-1">
            <CheckCircle2 className="size-3" /> Compatible versions
        </Badge>
    ) : (
        <Badge variant="outline" className="gap-1 text-warning-foreground">
            <AlertTriangle className="size-3" /> Inspect with caution
        </Badge>
    );
}

function CohortValue({ item }: { item: AnalyzerComparisonValue | null }) {
    if (item === null) {
        return <span className="text-muted-foreground">Not detected</span>;
    }

    return (
        <dl className="grid min-w-52 grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <dt className="text-muted-foreground">Videos</dt>
            <dd className="text-right tabular-nums">{item.sample_count}</dd>
            <dt className="text-muted-foreground">Median views</dt>
            <dd className="text-right tabular-nums">
                {value(item.median_views)} / {item.view_sample_count}
            </dd>
            <dt className="text-muted-foreground">Median views/day</dt>
            <dd className="text-right tabular-nums">
                {value(item.median_views_per_day)} /{' '}
                {item.views_per_day_sample_count}
            </dd>
            <dt className="text-muted-foreground">Breakout rate</dt>
            <dd className="text-right tabular-nums">
                {value(item.breakout_rate_percent, '%')} ({item.breakout_count}/
                {item.breakout_sample_count})
            </dd>
        </dl>
    );
}

function CohortTable({
    title,
    compatible,
    rows,
    runNames,
}: {
    title: string;
    compatible: boolean;
    rows: CrossChannelComparison['topic_rows'];
    runNames: string[];
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Exact observed cohort values and metric-specific
                            sample counts. Missing groups are not zero.
                        </p>
                    </div>
                    <CompatibilityBadge compatible={compatible} />
                </div>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <StatePanel
                        title={`${title} unavailable`}
                        description="Neither selected attempt has stored compatible cohort rows for this evidence type."
                    />
                ) : (
                    <Table>
                        <caption className="sr-only">
                            Exact {title.toLowerCase()} values for the selected
                            channel cohorts
                        </caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Detected group</TableHead>
                                {runNames.map((name, index) => (
                                    <TableHead key={`${name}-${index}`}>
                                        {name}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell className="max-w-64 font-medium whitespace-normal">
                                        {row.label}
                                    </TableCell>
                                    {row.values.map((item, index) => (
                                        <TableCell key={`${row.key}-${index}`}>
                                            <CohortValue item={item} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

export function CrossChannelComparisonView({
    comparison,
    timezone,
}: {
    comparison: CrossChannelComparison;
    timezone: string;
}) {
    const names = comparison.runs.map((run) => run.channel_title);

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Comparison evidence</CardTitle>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {comparison.disclaimer}
                    </p>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-3">
                    {comparison.runs.map((run) => (
                        <article
                            key={run.public_id}
                            className="rounded-xl border p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        {run.channel_title}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        YouTube ID: {run.provider_channel_id}
                                    </p>
                                </div>
                                <Link
                                    href={`/analyzer/runs/${run.public_id}`}
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    Open attempt{' '}
                                    <ExternalLink className="size-3" />
                                </Link>
                            </div>
                            <dl className="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                                <dt className="text-muted-foreground">
                                    Observed
                                </dt>
                                <dd>{formatDate(run.observed_at, timezone)}</dd>
                                <dt className="text-muted-foreground">
                                    Market context
                                </dt>
                                <dd>
                                    {run.market.key ?? 'Not frozen'} ·{' '}
                                    {run.market.source}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Source policy
                                </dt>
                                <dd className="capitalize">
                                    {run.cache_policy.replaceAll('_', ' ')}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Cohort
                                </dt>
                                <dd>
                                    {run.cohort_video_count} valid /{' '}
                                    {run.requested_video_count} requested
                                </dd>
                                <dt className="text-muted-foreground">
                                    Subscribers
                                </dt>
                                <dd>
                                    {run.subscriber_count_hidden
                                        ? 'Not public'
                                        : value(run.subscriber_count)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Size band
                                </dt>
                                <dd>{run.channel_size_band}</dd>
                                <dt className="text-muted-foreground">
                                    Freshness
                                </dt>
                                <dd>
                                    {run.freshness.state === 'stale'
                                        ? 'Stale observation'
                                        : 'Recent observation'}{' '}
                                    ({run.freshness.observed_age_hours}h old)
                                </dd>
                                <dt className="text-muted-foreground">
                                    Niche concentration
                                </dt>
                                <dd>
                                    {run.niche?.label ?? 'Not available'} ·{' '}
                                    {value(run.niche?.concentration_score ?? null)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Shorts share
                                </dt>
                                <dd>
                                    {value(run.shorts_share.percent, '%')} · n=
                                    {run.shorts_share.sample_count} ({run.shorts_share.state})
                                </dd>
                                <dt className="text-muted-foreground">
                                    Public engagement
                                </dt>
                                <dd title={run.public_engagement.reason}>
                                    {value(run.public_engagement.rate_percent, '%')} ({run.public_engagement.state})
                                </dd>
                                <dt className="text-muted-foreground">
                                    Channel model
                                </dt>
                                <dd className="break-all">
                                    {run.channel_model.calculation_version ??
                                        'Missing'}{' '}
                                    ·{' '}
                                    {run.channel_model.behavior_version ??
                                        'Missing'}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Topic model
                                </dt>
                                <dd className="break-all">
                                    {run.semantic_model?.calculation_version ??
                                        'Not analyzed'}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Thumbnail model
                                </dt>
                                <dd className="break-all">
                                    {run.thumbnail_model?.algorithm_version ??
                                        'Not analyzed'}
                                </dd>
                            </dl>
                            {run.peer_labels.length > 0 ? (
                                <ul
                                    className="mt-4 flex flex-wrap gap-2"
                                    aria-label="Stored peer evidence labels"
                                >
                                    {run.peer_labels.map((label) => (
                                        <li key={label.key}>
                                            <Badge
                                                variant="secondary"
                                                title={label.reason}
                                            >
                                                {label.label}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-4 text-xs text-muted-foreground">
                                    No conservative peer label is available
                                    from the stored evidence.
                                </p>
                            )}
                        </article>
                    ))}
                </CardContent>
            </Card>

            {comparison.compatibility.warnings.map((warning) => (
                <PartialDataBanner
                    key={warning.code}
                    title="Comparison compatibility warning"
                    description={warning.message}
                />
            ))}

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle>Channel behavior</CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Stored calculated metrics with their exact input
                                sample counts.
                            </p>
                        </div>
                        <CompatibilityBadge
                            compatible={
                                comparison.compatibility.channel_metrics
                            }
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <caption className="sr-only">
                            Exact channel behavior values for the selected
                            analyses
                        </caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Metric</TableHead>
                                {comparison.runs.map((run) => (
                                    <TableHead key={run.public_id}>
                                        {run.channel_title}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {comparison.channel_metrics.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell className="font-medium">
                                        {row.label}
                                    </TableCell>
                                    {row.values.map((item, index) => (
                                        <TableCell
                                            key={`${row.key}-${comparison.runs[index].public_id}`}
                                            className="tabular-nums"
                                        >
                                            {value(
                                                item.value,
                                                row.unit === 'percent'
                                                    ? '%'
                                                    : '',
                                            )}
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                n={item.sample_count}
                                            </span>
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <CohortTable
                title="Detected topic cohorts"
                compatible={comparison.compatibility.topics}
                rows={comparison.topic_rows}
                runNames={names}
            />
            <CohortTable
                title="Editorial title-pattern cohorts"
                compatible={comparison.compatibility.title_patterns}
                rows={comparison.title_pattern_rows}
                runNames={names}
            />
            <CohortTable
                title="Inferred thumbnail clusters"
                compatible={comparison.compatibility.thumbnails}
                rows={comparison.thumbnail_rows}
                runNames={names}
            />
        </div>
    );
}
