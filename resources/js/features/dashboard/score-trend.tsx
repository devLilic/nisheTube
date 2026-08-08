import { Link } from '@inertiajs/react';
import { Activity, ChevronDown } from 'lucide-react';
import { MarketBadge } from '@/components/market-badge';
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
import { show } from '@/routes/research/runs';
import type { DashboardTrendPoint } from '@/types';

const chartWidth = 640;
const chartHeight = 180;
const horizontalPadding = 24;
const verticalPadding = 18;

function pointCoordinates(points: DashboardTrendPoint[]) {
    const usableWidth = chartWidth - horizontalPadding * 2;
    const usableHeight = chartHeight - verticalPadding * 2;

    return points.map((point, index) => ({
        ...point,
        x:
            points.length === 1
                ? chartWidth / 2
                : horizontalPadding +
                  (index / (points.length - 1)) * usableWidth,
        y:
            verticalPadding +
            (1 - Math.min(100, Math.max(0, point.overall_score)) / 100) *
                usableHeight,
    }));
}

function formatDate(value: string | null, timezone: string) {
    if (!value) {
        return 'Unknown';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        timeZone: timezone,
    }).format(new Date(value));
}

export function ScoreTrend({
    points,
    timezone,
}: {
    points: DashboardTrendPoint[];
    timezone: string;
}) {
    const coordinates = pointCoordinates(points);
    const polyline = coordinates.map(({ x, y }) => `${x},${y}`).join(' ');

    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle>Opportunity score trend</CardTitle>
                        <CardDescription className="mt-1">
                            The latest {points.length} scored snapshots, ordered
                            by calculation time.
                        </CardDescription>
                    </div>
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <Activity className="size-4" aria-hidden="true" />
                    </span>
                </div>
            </CardHeader>
            <CardContent>
                {points.length === 0 ? (
                    <div className="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                        <Activity
                            className="size-7 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 text-sm font-medium">
                            No score trend yet
                        </p>
                        <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                            Complete a scored research run to establish the
                            first point.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="relative overflow-hidden rounded-xl border bg-linear-to-b from-primary/7 to-transparent p-3">
                            <svg
                                viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                                className="h-52 w-full"
                                aria-hidden="true"
                            >
                                {[25, 50, 75].map((score) => {
                                    const y =
                                        verticalPadding +
                                        (1 - score / 100) *
                                            (chartHeight - verticalPadding * 2);

                                    return (
                                        <g key={score}>
                                            <line
                                                x1={horizontalPadding}
                                                y1={y}
                                                x2={
                                                    chartWidth -
                                                    horizontalPadding
                                                }
                                                y2={y}
                                                className="stroke-border"
                                                strokeDasharray="4 6"
                                            />
                                            <text
                                                x={horizontalPadding}
                                                y={y - 6}
                                                className="fill-muted-foreground text-[10px]"
                                            >
                                                {score}
                                            </text>
                                        </g>
                                    );
                                })}
                                {coordinates.length > 1 && (
                                    <polyline
                                        points={polyline}
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        className="text-primary"
                                    />
                                )}
                                {coordinates.map((point) => (
                                    <g key={point.public_id}>
                                        <circle
                                            cx={point.x}
                                            cy={point.y}
                                            r="7"
                                            className="fill-background stroke-primary"
                                            strokeWidth="4"
                                        />
                                        <text
                                            x={point.x}
                                            y={point.y - 14}
                                            textAnchor="middle"
                                            className="fill-foreground text-[11px] font-semibold"
                                        >
                                            {point.overall_score.toFixed(1)}
                                        </text>
                                    </g>
                                ))}
                            </svg>
                            <div className="flex items-center justify-between gap-3 px-2 text-xs text-muted-foreground">
                                <span>
                                    {formatDate(
                                        points[0]?.calculated_at ?? null,
                                        timezone,
                                    )}
                                </span>
                                <span>
                                    {formatDate(
                                        points.at(-1)?.calculated_at ?? null,
                                        timezone,
                                    )}
                                </span>
                            </div>
                        </div>

                        <details className="group mt-4 rounded-lg border px-4 py-3">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                View exact trend values
                                <ChevronDown
                                    className="size-4 text-muted-foreground transition-transform group-open:rotate-180"
                                    aria-hidden="true"
                                />
                            </summary>
                            <div className="mt-3 border-t pt-2">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Snapshot</TableHead>
                                            <TableHead>Market</TableHead>
                                            <TableHead className="text-right">
                                                Score
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Confidence
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {points.map((point) => (
                                            <TableRow key={point.public_id}>
                                                <TableCell>
                                                    <Link
                                                        href={show(
                                                            point.public_id,
                                                        )}
                                                        className="font-medium hover:text-primary hover:underline"
                                                    >
                                                        {point.query_text}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {formatDate(
                                                            point.calculated_at,
                                                            timezone,
                                                        )}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <MarketBadge
                                                        market={
                                                            point.market_key
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {point.overall_score.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {point.confidence_score.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </details>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
