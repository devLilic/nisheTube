import { Link } from '@inertiajs/react';
import { ExternalLink, ScanSearch, Search } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    AnalyzerBreakoutClass,
    AnalyzerRecentVideo,
    AnalyzerRun,
} from '@/types';

const numbers = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

function value(input: number | null, suffix = '') {
    return input === null ? 'Unavailable' : `${numbers.format(input)}${suffix}`;
}

function duration(seconds: number | null) {
    if (seconds === null) {
        return 'Unavailable';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`
        : `${minutes}:${String(remainder).padStart(2, '0')}`;
}

function observed(input: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(input));
}

type SortKey =
    | 'position'
    | 'published'
    | 'views'
    | 'views_per_day'
    | 'duration'
    | 'relative';

type ClassFilter = 'all' | AnalyzerBreakoutClass['value'];
type PublishedFilter = 'recent_window' | 'all';

function sortValue(video: AnalyzerRecentVideo, key: SortKey) {
    if (key === 'position') {
        return video.source_position;
    }

    if (key === 'published') {
        return new Date(video.published_at).getTime();
    }

    if (key === 'views') {
        return video.view_count ?? -1;
    }

    if (key === 'views_per_day') {
        return video.lifetime_views_per_day ?? -1;
    }

    if (key === 'relative') {
        return video.channel_median_ratio ?? -1;
    }

    return video.duration_seconds ?? -1;
}

export function AnalyzerChannelCohort({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState<SortKey>('position');
    const [classFilter, setClassFilter] = useState<ClassFilter>('all');
    const [publishedFilter, setPublishedFilter] =
        useState<PublishedFilter>('recent_window');
    const metrics = run.channel_metrics;
    const videos = useMemo(() => run.recent_videos ?? [], [run.recent_videos]);
    const filtered = useMemo(() => {
        const normalized = query.trim().toLocaleLowerCase();

        return videos
            .filter((video) => {
                const matchesQuery =
                    normalized === '' ||
                    `${video.title} ${video.category?.name ?? ''}`
                        .toLocaleLowerCase()
                        .includes(normalized);
                const matchesClass =
                    classFilter === 'all' ||
                    video.breakout_class?.value === classFilter;
                const matchesPublished =
                    publishedFilter === 'all' ||
                    video.published_within_recent_window;

                return matchesQuery && matchesClass && matchesPublished;
            })
            .toSorted((left, right) => {
                const direction = sort === 'position' ? 1 : -1;
                const difference =
                    sortValue(left, sort) - sortValue(right, sort);

                return difference === 0
                    ? left.source_position - right.source_position
                    : difference * direction;
            });
    }, [classFilter, publishedFilter, query, sort, videos]);
    const recentWindowCount = videos.filter(
        (video) => video.published_within_recent_window,
    ).length;
    const outliers = videos.filter((video) =>
        ['strong', 'breakout'].includes(video.breakout_class?.value ?? ''),
    );

    if (!metrics) {
        return run.is_active ? null : (
            <StatePanel
                title="Recent channel baseline unavailable"
                description="The channel did not provide a usable uploads cohort for this immutable attempt. Anchor and channel profile data remain available above."
            />
        );
    }

    return (
        <section
            className="space-y-6"
            aria-labelledby="channel-baseline-heading"
        >
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="channel-baseline-heading">
                                Recent Channel Baseline
                            </CardTitle>
                            <CardDescription className="mt-1">
                                Frozen cohort of {metrics.recent_valid_count}{' '}
                                valid uploads from a requested limit of{' '}
                                {metrics.recent_requested_count}.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">Calculated Metrics</Badge>
                            <Badge variant="outline">
                                {value(metrics.coverage_percent, '%')} coverage
                            </Badge>
                            <Badge variant="outline">
                                {metrics.threshold_version ??
                                    run.relative_context?.threshold_version ??
                                    'Threshold unavailable'}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            ['Median views', value(metrics.median_views)],
                            ['Average views', value(metrics.average_views)],
                            [
                                'View range',
                                `${value(metrics.minimum_views)} – ${value(metrics.maximum_views)}`,
                            ],
                            ['Median likes', value(metrics.median_likes)],
                            ['Median comments', value(metrics.median_comments)],
                            [
                                'Median duration',
                                duration(metrics.median_duration_seconds),
                            ],
                            [
                                'Average duration',
                                duration(metrics.average_duration_seconds),
                            ],
                            [
                                'Duration range',
                                `${duration(metrics.minimum_duration_seconds)} – ${duration(metrics.maximum_duration_seconds)}`,
                            ],
                            [
                                'Median video age',
                                value(metrics.median_age_days, ' days'),
                            ],
                            ['Videos / week', value(metrics.videos_per_week)],
                            ['Videos / month', value(metrics.videos_per_month)],
                            [
                                'Median upload gap',
                                value(metrics.median_upload_gap_days, ' days'),
                            ],
                            [
                                'Average upload gap',
                                value(metrics.average_upload_gap_days, ' days'),
                            ],
                            [
                                'Longest upload gap',
                                value(metrics.longest_upload_gap_days, ' days'),
                            ],
                            [
                                'Strong share (≥3x)',
                                metrics.strong_count === null
                                    ? 'Unavailable'
                                    : `${value(metrics.strong_share_percent, '%')} (${metrics.strong_count})`,
                            ],
                            [
                                'Breakout share (>5x)',
                                metrics.breakout_count === null
                                    ? 'Unavailable'
                                    : `${value(metrics.breakout_share_percent, '%')} (${metrics.breakout_count})`,
                            ],
                        ].map(([label, metricValue]) => (
                            <div
                                key={label}
                                className="rounded-xl border bg-muted/20 p-4"
                            >
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {label}
                                </dt>
                                <dd className="mt-2 font-semibold break-words tabular-nums">
                                    {metricValue}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="rounded-xl border p-4">
                            <h3 className="font-medium">Duration profile</h3>
                            <dl className="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                                {Object.entries(
                                    metrics.duration_distribution,
                                ).map(([bucket, count]) => (
                                    <div key={bucket}>
                                        <dt className="text-muted-foreground">
                                            {bucket.replaceAll('_', ' ')}
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {count}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                        <div className="rounded-xl border p-4">
                            <h3 className="font-medium">
                                Official category profile
                            </h3>
                            {metrics.category_distribution.length === 0 ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Category data is unavailable.
                                </p>
                            ) : (
                                <ul className="mt-3 flex flex-wrap gap-2">
                                    {metrics.category_distribution.map(
                                        (category) => (
                                            <li key={category.id}>
                                                <Badge variant="secondary">
                                                    {category.name ??
                                                        `Category ${category.id}`}
                                                    : {category.count}
                                                </Badge>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Calculated {observed(metrics.calculated_at, timezone)} ·{' '}
                        {metrics.calculation_version}. Median is the primary
                        baseline; averages and ranges provide supporting
                        context.
                    </p>
                </CardContent>
            </Card>

            {metrics.warnings.map((warning) => (
                <PartialDataBanner
                    key={warning}
                    title="Recent baseline coverage"
                    description={warning}
                />
            ))}

            <Card>
                <CardHeader>
                    <CardTitle>Strong / Breakout Outliers</CardTitle>
                    <CardDescription>
                        Videos at least{' '}
                        {run.relative_context?.strong_ratio ?? 3}x the recent
                        channel median. Breakout is strictly above{' '}
                        {run.relative_context?.breakout_ratio_exclusive ?? 5}x.
                        Raw-view classes always include age and Lifetime Average
                        Views/Day context.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {outliers.length === 0 ? (
                        <StatePanel
                            title="No Strong or Breakout outliers"
                            description="No valid recent video crossed the configured Strong threshold in this frozen cohort."
                        />
                    ) : (
                        <ul className="grid gap-3 lg:grid-cols-2">
                            {outliers.map((video) => (
                                <li
                                    key={video.provider_video_id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <a
                                            href={video.youtube_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-medium break-words hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {video.title}
                                        </a>
                                        <Badge variant="outline">
                                            {video.breakout_class?.label}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground tabular-nums">
                                        {value(
                                            video.channel_median_ratio,
                                            'x median',
                                        )}{' '}
                                        ·{' '}
                                        {value(
                                            video.age_seconds === null
                                                ? null
                                                : Math.floor(
                                                      video.age_seconds /
                                                          86_400,
                                                  ),
                                            ' days old',
                                        )}{' '}
                                        ·{' '}
                                        {value(
                                            video.lifetime_views_per_day,
                                            ' Lifetime Average Views/Day',
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle>Recent Videos</CardTitle>
                            <CardDescription className="mt-1">
                                Playlist position preserves source order; it is
                                not a performance rank. Exact values use the
                                snapshot pinned to this attempt.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">
                            {recentWindowCount} in the last{' '}
                            {run.recent_video_window.days} days
                        </Badge>
                    </div>
                    <CardDescription>
                        The window is measured in UTC from this immutable
                        attempt
                        {run.recent_video_window.reference_at
                            ? ` (${observed(run.recent_video_window.reference_at, timezone)})`
                            : ''}
                        . It filters the bounded stored cohort; it does not
                        fetch or claim every channel upload in that period.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_13rem_15rem_15rem]">
                        <label className="relative">
                            <span className="sr-only">
                                Filter recent videos
                            </span>
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Filter by title or category"
                                className="pl-9"
                            />
                        </label>
                        <label className="grid gap-1 text-xs font-medium text-muted-foreground">
                            <span className="sr-only">
                                Filter by publication window
                            </span>
                            <select
                                value={publishedFilter}
                                onChange={(event) =>
                                    setPublishedFilter(
                                        event.target.value as PublishedFilter,
                                    )
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="recent_window">
                                    Last 90 days
                                </option>
                                <option value="all">All stored videos</option>
                            </select>
                        </label>
                        <label className="grid gap-1 text-xs font-medium text-muted-foreground">
                            <span className="sr-only">Sort recent videos</span>
                            <select
                                value={sort}
                                onChange={(event) =>
                                    setSort(event.target.value as SortKey)
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="position">
                                    Playlist position
                                </option>
                                <option value="published">
                                    Newest published
                                </option>
                                <option value="views">Most views</option>
                                <option value="views_per_day">
                                    Highest lifetime views/day
                                </option>
                                <option value="duration">
                                    Longest duration
                                </option>
                                <option value="relative">
                                    Highest channel-relative ratio
                                </option>
                            </select>
                        </label>
                        <label className="grid gap-1 text-xs font-medium text-muted-foreground">
                            <span className="sr-only">
                                Filter by relative class
                            </span>
                            <select
                                value={classFilter}
                                onChange={(event) =>
                                    setClassFilter(
                                        event.target.value as ClassFilter,
                                    )
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="all">
                                    All relative classes
                                </option>
                                <option value="underperformer">
                                    Underperformer
                                </option>
                                <option value="normal">Normal</option>
                                <option value="above_average">
                                    Above Average
                                </option>
                                <option value="strong">Strong</option>
                                <option value="breakout">Breakout</option>
                            </select>
                        </label>
                    </div>

                    {filtered.length === 0 ? (
                        <StatePanel
                            title={
                                videos.length === 0
                                    ? 'No valid recent uploads'
                                    : publishedFilter === 'recent_window' &&
                                        recentWindowCount === 0
                                      ? 'No stored videos in the last 90 days'
                                      : 'No matching recent videos'
                            }
                            description={
                                videos.length === 0
                                    ? 'Playlist items were unavailable or omitted required video details.'
                                    : publishedFilter === 'recent_window' &&
                                        recentWindowCount === 0
                                      ? 'This bounded stored cohort contains no videos published during the 90 days before this analysis attempt. Choose All stored videos to review older evidence.'
                                      : 'Clear or change the title/category filter.'
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Video</TableHead>
                                    <TableHead>Published</TableHead>
                                    <TableHead className="text-right">
                                        Views
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Lifetime views/day
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Age
                                    </TableHead>
                                    <TableHead>Relative class</TableHead>
                                    <TableHead className="text-right">
                                        Likes / comments
                                    </TableHead>
                                    <TableHead>Duration</TableHead>
                                    <TableHead>Source</TableHead>
                                    <TableHead className="text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((video) => (
                                    <TableRow key={video.provider_video_id}>
                                        <TableCell className="max-w-xl min-w-72">
                                            <a
                                                href={video.youtube_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-start gap-1.5 font-medium break-words hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <span>
                                                    #{video.source_position}{' '}
                                                    {video.title}
                                                </span>
                                                <ExternalLink className="mt-0.5 size-3.5 shrink-0" />
                                            </a>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {video.category?.name ??
                                                    'Category unavailable'}
                                            </p>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {observed(
                                                video.published_at,
                                                timezone,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {value(video.view_count)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {value(
                                                video.lifetime_views_per_day,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {value(
                                                video.age_seconds === null
                                                    ? null
                                                    : Math.floor(
                                                          video.age_seconds /
                                                              86_400,
                                                      ),
                                                ' days',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex min-w-28 flex-col items-start gap-1">
                                                <Badge variant="outline">
                                                    {video.breakout_class
                                                        ?.label ??
                                                        'Unavailable'}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {value(
                                                        video.channel_median_ratio,
                                                        'x median',
                                                    )}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {value(video.like_count)} /{' '}
                                            {value(video.comment_count)}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {duration(video.duration_seconds)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {video.source_mode}
                                            </Badge>
                                            <span className="sr-only">
                                                {' '}
                                                observed{' '}
                                                {observed(
                                                    video.observed_at,
                                                    timezone,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                {video.local_analysis ? (
                                                    <Link
                                                        href={`${video.local_analysis.url}?return_to=${encodeURIComponent(`/analyzer/runs/${run.public_id}`)}`}
                                                        aria-label={`View stored data for ${video.title}`}
                                                    >
                                                        View data
                                                    </Link>
                                                ) : (
                                                    <Link
                                                        href={`/analyzer?video=${encodeURIComponent(video.provider_video_id)}&return_to=${encodeURIComponent(`/analyzer/runs/${run.public_id}`)}`}
                                                        aria-label={`Analyze ${video.title}`}
                                                    >
                                                        <ScanSearch /> Analyze
                                                    </Link>
                                                )}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <p
                        aria-live="polite"
                        className="text-xs text-muted-foreground"
                    >
                        Showing {filtered.length} of {videos.length} stored
                        videos. Filtering and opening Analyzer make no provider
                        request until you submit the new analysis.
                    </p>
                </CardContent>
            </Card>
        </section>
    );
}
