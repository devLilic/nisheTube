import { Gauge, RadioTower, UsersRound, Video } from 'lucide-react';
import { MetricCard } from '@/components/metric-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ResearchAnalysisSummary } from '@/types';
import {
    formatAnalysisTimestamp,
    formatDecimal,
    formatInteger,
} from './analysis-format';

export function AnalysisOverview({
    summary,
    timezone,
}: {
    summary: ResearchAnalysisSummary;
    timezone: string;
}) {
    return (
        <section
            aria-labelledby="analysis-overview-title"
            className="space-y-4"
        >
            <Card className="gap-4 border-primary/20 bg-primary/[0.025] py-5">
                <CardHeader className="gap-3 px-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle id="analysis-overview-title">
                            Observed-demand snapshot
                        </CardTitle>
                        <CardDescription className="mt-1 max-w-2xl leading-6">
                            Aggregates use only videos returned and enriched in
                            this run. They do not represent YouTube search
                            volume.
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="w-fit tabular-nums">
                        {formatDecimal(summary.coverage_percent)}% coverage
                    </Badge>
                </CardHeader>
                <CardContent className="px-5">
                    <dl className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="text-muted-foreground">Videos</dt>
                            <dd className="mt-1 font-semibold tabular-nums">
                                {formatInteger(summary.video_count)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Channels</dt>
                            <dd className="mt-1 font-semibold tabular-nums">
                                {formatInteger(summary.channel_count)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Metrics collected
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {formatAnalysisTimestamp(
                                    summary.latest_collected_at,
                                    timezone,
                                )}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Median video views"
                    value={formatInteger(summary.median_views)}
                    detail="Exact median in this run"
                    icon={Video}
                />
                <MetricCard
                    label="Median daily velocity"
                    value={formatDecimal(summary.median_views_per_day)}
                    detail="Views per day since publish"
                    icon={Gauge}
                />
                <MetricCard
                    label="Median subscribers"
                    value={formatInteger(summary.median_subscribers)}
                    detail="Visible channel counts only"
                    icon={UsersRound}
                />
                <MetricCard
                    label="Median reach ratio"
                    value={formatDecimal(summary.median_reach_ratio, 2)}
                    detail="Video views ÷ subscribers"
                    icon={RadioTower}
                />
            </div>
        </section>
    );
}
