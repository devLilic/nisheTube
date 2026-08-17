import { Link, router } from '@inertiajs/react';
import {
    ArrowDownUp,
    ChevronLeft,
    ChevronRight,
    ScanSearch,
} from 'lucide-react';
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
import type { ResearchEvidenceInspection } from '@/types';

const sortOptions = [
    ['relevance', 'Relevance'],
    ['views_per_day', 'Views per day'],
    ['engagement', 'Engagement'],
    ['channel_size', 'Channel size'],
    ['reach_ratio', 'Reach ratio'],
    ['published_at', 'Publish date'],
    ['breakout_class', 'Breakout class'],
] as const;

function formatNumber(value: number | null, digits = 0) {
    return value === null
        ? 'Not available'
        : new Intl.NumberFormat('en-US', {
              maximumFractionDigits: digits,
          }).format(value);
}

function formatDate(value: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeZone: timezone,
    }).format(new Date(value));
}

export function ResearchEvidenceInspection({
    inspection,
    runPublicId,
    timezone,
}: {
    inspection: ResearchEvidenceInspection | undefined;
    runPublicId: string;
    timezone: string;
}) {
    if (!inspection) {
        return null;
    }

    const update = (patch: Record<string, string | number>) => {
        router.get(
            `/research/runs/${runPublicId}`,
            {
                evidence_sort: inspection.query.sort,
                evidence_direction: inspection.query.direction,
                evidence_filter: inspection.query.filter,
                evidence_page: inspection.pagination.page,
                ...patch,
            },
            {
                only: ['run'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <section aria-labelledby="research-evidence-title">
            <Card className="overflow-hidden">
                <CardHeader className="border-b">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <CardTitle id="research-evidence-title">
                                Video evidence inspection
                            </CardTitle>
                            <CardDescription className="mt-1 max-w-3xl">
                                Server-filtered immutable rows, bounded to{' '}
                                {inspection.limits.page_size} per page. Exact
                                values remain available; inspecting this table
                                never calls YouTube.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-end gap-2">
                            <label className="grid gap-1 text-xs font-medium">
                                Sort evidence
                                <select
                                    value={inspection.query.sort}
                                    onChange={(event) =>
                                        update({
                                            evidence_sort: event.target.value,
                                            evidence_page: 1,
                                        })
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    {sortOptions.map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    update({
                                        evidence_direction:
                                            inspection.query.direction === 'asc'
                                                ? 'desc'
                                                : 'asc',
                                        evidence_page: 1,
                                    })
                                }
                                aria-label={`Sort ${inspection.query.direction === 'asc' ? 'descending' : 'ascending'}`}
                            >
                                <ArrowDownUp aria-hidden="true" />
                                {inspection.query.direction === 'asc'
                                    ? 'Ascending'
                                    : 'Descending'}
                            </Button>
                        </div>
                    </div>

                    <div
                        className="flex flex-wrap gap-2"
                        aria-label="Evidence quick filters"
                    >
                        {inspection.filters.map((filter) => (
                            <Button
                                key={filter.key}
                                type="button"
                                size="sm"
                                variant={
                                    inspection.query.filter === filter.key
                                        ? 'secondary'
                                        : 'outline'
                                }
                                disabled={!filter.enabled}
                                title={filter.reason ?? undefined}
                                onClick={() =>
                                    update({
                                        evidence_filter: filter.key,
                                        evidence_page: 1,
                                    })
                                }
                            >
                                {filter.label}
                            </Button>
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {inspection.relevance.description}
                    </p>
                </CardHeader>

                <CardContent className="p-0">
                    {inspection.items.length === 0 ? (
                        <div className="flex min-h-44 flex-col items-center justify-center px-6 text-center">
                            <p className="font-medium">
                                No evidence matches this filter
                            </p>
                            <p className="mt-1 max-w-lg text-sm text-muted-foreground">
                                Missing metrics stay unavailable and are never
                                treated as zero. Choose All evidence to inspect
                                every stored row.
                            </p>
                            {inspection.query.filter !== 'all' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() =>
                                        update({
                                            evidence_filter: 'all',
                                            evidence_page: 1,
                                        })
                                    }
                                >
                                    Clear filter
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <caption className="sr-only">
                                    Exact stored video evidence sorted and
                                    filtered on the server.
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="min-w-80">
                                            Video
                                        </TableHead>
                                        <TableHead>Relevance</TableHead>
                                        <TableHead className="text-right">
                                            Views/day
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Engagement
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Channel size
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Reach ratio
                                        </TableHead>
                                        <TableHead>Published</TableHead>
                                        <TableHead>Breakout</TableHead>
                                        <TableHead>
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {inspection.items.map((item) => (
                                        <TableRow key={item.provider_video_id}>
                                            <TableCell className="max-w-[32rem] align-top">
                                                <p
                                                    className="font-medium break-words"
                                                    title={item.title}
                                                >
                                                    {item.title}
                                                </p>
                                                <p className="mt-1 text-xs break-words text-muted-foreground">
                                                    #{item.result_rank} ·{' '}
                                                    {item.channel_title} ·{' '}
                                                    {item.format === 'long_form'
                                                        ? 'Long-form'
                                                        : item.format ===
                                                            'shorts'
                                                          ? 'Shorts'
                                                          : 'Format unavailable'}
                                                </p>
                                                <details className="mt-2 text-xs">
                                                    <summary className="cursor-pointer font-medium text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                                        Exact row context
                                                    </summary>
                                                    <dl className="mt-2 grid gap-1 text-muted-foreground">
                                                        <div>
                                                            Views:{' '}
                                                            {formatNumber(
                                                                item.view_count,
                                                            )}
                                                        </div>
                                                        <div>
                                                            Collected:{' '}
                                                            {item.collected_at
                                                                ? formatDate(
                                                                      item.collected_at,
                                                                      timezone,
                                                                  )
                                                                : 'Not available'}
                                                        </div>
                                                        <div>
                                                            Metrics:{' '}
                                                            {item.metrics_complete
                                                                ? 'Complete'
                                                                : 'Partial'}
                                                        </div>
                                                    </dl>
                                                </details>
                                            </TableCell>
                                            <TableCell>
                                                {item.relevance ? (
                                                    <details>
                                                        <summary className="cursor-pointer list-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                                            <Badge
                                                                variant="outline"
                                                                className="capitalize"
                                                            >
                                                                {item.relevance.class.replaceAll(
                                                                    '_',
                                                                    ' ',
                                                                )}{' '}
                                                                ·{' '}
                                                                {formatNumber(
                                                                    item
                                                                        .relevance
                                                                        .score,
                                                                    1,
                                                                )}
                                                            </Badge>
                                                        </summary>
                                                        <dl className="mt-2 min-w-48 space-y-1 text-xs text-muted-foreground">
                                                            <div>
                                                                Title coverage:{' '}
                                                                {item.relevance
                                                                    .signals
                                                                    .title_coverage ==
                                                                null
                                                                    ? 'Not available'
                                                                    : `${formatNumber(item.relevance.signals.title_coverage * 100, 1)}%`}
                                                            </div>
                                                            <div>
                                                                Semantic/category/topic
                                                                matches:{' '}
                                                                {(item.relevance
                                                                    .signals
                                                                    .semantic_matches ??
                                                                    0) +
                                                                    (item
                                                                        .relevance
                                                                        .signals
                                                                        .category_matches ??
                                                                        0) +
                                                                    (item
                                                                        .relevance
                                                                        .signals
                                                                        .topic_matches ??
                                                                        0)}
                                                            </div>
                                                            <div>
                                                                Language:{' '}
                                                                {item.relevance
                                                                    .signals
                                                                    .language_match
                                                                    ? 'Compatible'
                                                                    : 'Mismatch or unavailable'}
                                                            </div>
                                                            <div>
                                                                Format:{' '}
                                                                {item.relevance
                                                                    .signals
                                                                    .format_match ==
                                                                null
                                                                    ? 'Unavailable'
                                                                    : item
                                                                            .relevance
                                                                            .signals
                                                                            .format_match
                                                                      ? 'Compatible'
                                                                      : 'Mismatch'}
                                                            </div>
                                                            <div>
                                                                Negative terms:{' '}
                                                                {item.relevance.signals.negative_matches?.join(
                                                                    ', ',
                                                                ) || 'None'}
                                                            </div>
                                                        </dl>
                                                    </details>
                                                ) : (
                                                    <span className="tabular-nums">
                                                        Provider rank #
                                                        {item.result_rank}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(
                                                    item.views_per_day,
                                                    2,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.engagement_rate === null
                                                    ? 'Not available'
                                                    : `${formatNumber(item.engagement_rate, 2)}%`}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.subscriber_count_hidden
                                                    ? 'Hidden'
                                                    : formatNumber(
                                                          item.subscriber_count,
                                                      )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(
                                                    item.reach_ratio,
                                                    4,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    item.published_at,
                                                    timezone,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {item.breakout_class ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="capitalize"
                                                    >
                                                        {item.breakout_class.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    'Not measured'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/analyzer?video=${encodeURIComponent(item.provider_video_id)}&origin=search&origin_reference=${encodeURIComponent(runPublicId)}&return_to=${encodeURIComponent(`/research/runs/${runPublicId}?evidence_sort=${inspection.query.sort}&evidence_direction=${inspection.query.direction}&evidence_filter=${inspection.query.filter}&evidence_page=${inspection.pagination.page}`)}`}
                                                        aria-label={`Open ${item.title} in Analyzer`}
                                                    >
                                                        <ScanSearch aria-hidden="true" />{' '}
                                                        Analyze
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4">
                        <p
                            className="text-xs text-muted-foreground"
                            role="status"
                        >
                            Showing {inspection.pagination.from}–
                            {inspection.pagination.to} of{' '}
                            {inspection.pagination.total} stored rows
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={inspection.pagination.page <= 1}
                                onClick={() =>
                                    update({
                                        evidence_page:
                                            inspection.pagination.page - 1,
                                    })
                                }
                            >
                                <ChevronLeft aria-hidden="true" /> Previous
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    inspection.pagination.page >=
                                    inspection.pagination.last_page
                                }
                                onClick={() =>
                                    update({
                                        evidence_page:
                                            inspection.pagination.page + 1,
                                    })
                                }
                            >
                                Next <ChevronRight aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>
    );
}
