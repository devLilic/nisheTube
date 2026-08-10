import { router } from '@inertiajs/react';
import { AlertTriangle, BarChart3, FileDown, Layers3 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { StatePanel } from '@/components/data-state';
import { PartialDataBanner } from '@/components/partial-data-banner';
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
import type {
    AnalyzerRun,
    AnalyzerTopicPerformanceAggregate,
} from '@/types/analyzer';

const exactNumber = new Intl.NumberFormat('en', { maximumFractionDigits: 6 });

function value(number: number | null) {
    return number === null ? 'Insufficient sample' : exactNumber.format(number);
}

function timestamp(input: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(input));
}

function performanceLabel(row: AnalyzerTopicPerformanceAggregate) {
    return row.group_type === 'topic' ? 'Detected topic' : 'Editorial pattern';
}

export function TopicPerformance({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const profile = run.topic_performance;
    const [groupType, setGroupType] = useState<'topic' | 'title_pattern'>(
        'topic',
    );
    const rows = useMemo(
        () =>
            (profile?.aggregates ?? []).filter(
                (aggregate) => aggregate.group_type === groupType,
            ),
        [groupType, profile?.aggregates],
    );
    const maximumMedian = Math.max(
        0,
        ...rows.map((row) => row.median_views ?? 0),
    );

    if (!profile && run.is_active) {
        return (
            <div aria-busy="true">
                <StatePanel
                    title="Calculating topic performance"
                    description="Stored recent-cohort observations will be grouped after detected topics and editorial title patterns are ready."
                    icon={BarChart3}
                />
            </div>
        );
    }

    if (!profile) {
        return (
            <StatePanel
                title="Topic performance unavailable"
                description="This attempt has no versioned topic or title-pattern performance result."
                icon={Layers3}
            />
        );
    }

    if (profile.status === 'failed') {
        return (
            <StatePanel
                title="Topic performance could not be calculated"
                description="The stored Analyzer observations remain safe. A new immutable refresh can calculate a new performance version."
                icon={AlertTriangle}
            />
        );
    }

    return (
        <section
            aria-labelledby="topic-performance-title"
            className="space-y-3"
        >
            {profile.status !== 'complete' && (
                <PartialDataBanner
                    title={
                        profile.status === 'insufficient'
                            ? 'Insufficient topic performance sample'
                            : 'Partial topic performance evidence'
                    }
                    description={
                        profile.warnings[0] ??
                        `Groups need at least ${profile.minimum_sample_size} recent videos before performance values are shown.`
                    }
                />
            )}
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="topic-performance-title">
                                Topic and title-pattern performance
                            </CardTitle>
                            <CardDescription className="mt-1 max-w-3xl">
                                Observed association within this frozen recent
                                cohort, not evidence that a topic or title
                                pattern caused performance. Missing metrics and
                                groups below the minimum sample stay explicit.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">Inferred groups</Badge>
                            {!run.is_active && (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/analyzer/runs/${run.public_id}/performance-export`,
                                                { format: 'csv' },
                                            )
                                        }
                                    >
                                        <FileDown /> Queue CSV
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/analyzer/runs/${run.public_id}/performance-export`,
                                                { format: 'xlsx' },
                                            )
                                        }
                                    >
                                        <FileDown /> Queue XLSX
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <label className="grid gap-1.5 text-sm font-medium">
                            Evidence grouping
                            <Select
                                value={groupType}
                                onValueChange={(next) =>
                                    setGroupType(
                                        next as 'topic' | 'title_pattern',
                                    )
                                }
                            >
                                <SelectTrigger className="w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="topic">
                                        Detected topics
                                    </SelectItem>
                                    <SelectItem value="title_pattern">
                                        Editorial title patterns
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>
                        <p className="text-xs text-muted-foreground">
                            Minimum sample: {profile.minimum_sample_size} ·
                            Cohort: {profile.cohort_video_count} videos
                        </p>
                    </div>

                    {rows.length === 0 ? (
                        <StatePanel
                            title="No groups in this view"
                            description="No stored recent-cohort title could be assigned to this grouping."
                            icon={Layers3}
                        />
                    ) : (
                        <>
                            <div
                                aria-label={`${groupType === 'topic' ? 'Detected topic' : 'Editorial title pattern'} median views chart`}
                                className="space-y-3 rounded-xl border bg-muted/10 p-4"
                            >
                                {rows.map((row) => (
                                    <div
                                        key={`${row.group_type}:${row.key}`}
                                        className="grid gap-1 sm:grid-cols-[minmax(9rem,0.45fr)_1fr_auto] sm:items-center"
                                    >
                                        <span className="truncate text-sm font-medium">
                                            {row.label}
                                        </span>
                                        <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width:
                                                        row.median_views ===
                                                            null ||
                                                        maximumMedian === 0
                                                            ? '0%'
                                                            : `${Math.max(3, (row.median_views / maximumMedian) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            Median {value(row.median_views)}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <Table>
                                <caption className="sr-only">
                                    Exact topic and editorial title-pattern
                                    performance values for the frozen Analyzer
                                    cohort.
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Group</TableHead>
                                        <TableHead>Videos</TableHead>
                                        <TableHead>Median views</TableHead>
                                        <TableHead>Average views</TableHead>
                                        <TableHead>
                                            Median lifetime views/day
                                        </TableHead>
                                        <TableHead>
                                            Average lifetime views/day
                                        </TableHead>
                                        <TableHead>Breakout rate</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow
                                            key={`${row.group_type}:${row.key}`}
                                        >
                                            <TableCell className="min-w-48">
                                                <div className="font-medium">
                                                    {row.label}
                                                </div>
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    <Badge variant="outline">
                                                        {performanceLabel(row)}
                                                    </Badge>
                                                    {row.is_unclassified && (
                                                        <Badge variant="secondary">
                                                            Unclassified
                                                        </Badge>
                                                    )}
                                                    {!row.meets_minimum_sample && (
                                                        <Badge variant="secondary">
                                                            Below minimum
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {row.sample_count}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {value(row.median_views)}
                                                <span className="block text-xs text-muted-foreground">
                                                    n={row.view_sample_count}
                                                </span>
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {value(row.average_views)}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {value(
                                                    row.median_views_per_day,
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    n=
                                                    {
                                                        row.views_per_day_sample_count
                                                    }
                                                </span>
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {value(
                                                    row.average_views_per_day,
                                                )}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {row.breakout_rate_percent ===
                                                null
                                                    ? 'Insufficient sample'
                                                    : `${row.breakout_rate_percent.toFixed(1)}%`}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.breakout_count} of{' '}
                                                    {row.breakout_sample_count}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </>
                    )}

                    <p className="text-xs leading-5 text-muted-foreground">
                        Performance version: {profile.calculation_version} ·
                        Topic version: {profile.topic_version ?? 'Unavailable'}
                        {' · '}Title-pattern version:{' '}
                        {profile.title_pattern_version} · Calculated{' '}
                        {timestamp(profile.calculated_at, timezone)}
                    </p>
                </CardContent>
            </Card>
        </section>
    );
}
