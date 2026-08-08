import { Head } from '@inertiajs/react';
import { Activity, Eye, Layers3, Search, Sparkles } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '@/components/data-state';
import { MarketBadge } from '@/components/market-badge';
import { MetricCard } from '@/components/metric-card';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { PartialDataBanner } from '@/components/partial-data-banner';
import { RunStatus } from '@/components/run-status';
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

const fixtureRuns = [
    {
        query: 'small studio lighting',
        market: 'global_en' as const,
        status: 'completed' as const,
        observedViews: '2,481,309',
        score: '78',
    },
    {
        query: 'idei afaceri locale',
        market: 'ro_ro' as const,
        status: 'partial' as const,
        observedViews: '184,220',
        score: '64',
    },
    {
        query: 'обзор компактных камер для путешествий',
        market: 'ru_ru' as const,
        status: 'searching' as const,
        observedViews: '—',
        score: '—',
    },
];

export default function DesignSystem() {
    return (
        <>
            <Head title="UI showcase" />
            <PageContainer>
                <PageHeader
                    eyebrow="Foundation · FND-04"
                    title="Research interface system"
                    description="Reusable patterns for clear, honest YouTube research—built to distinguish observed signals, missing data, and collection status at a glance. All values on this page are fixtures."
                    actions={
                        <>
                            <Button variant="outline">Secondary action</Button>
                            <Button>
                                <Search />
                                Primary action
                            </Button>
                        </>
                    }
                />

                <section aria-labelledby="metrics-heading">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2
                                id="metrics-heading"
                                className="text-lg font-semibold"
                            >
                                Metric cards
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Summary values retain source context and exact
                                values.
                            </p>
                        </div>
                        <Badge variant="secondary">Fixture data</Badge>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            label="Observed views"
                            value="2,481,309"
                            detail="25 returned videos"
                            icon={Eye}
                            delta="12.4%"
                            trend="up"
                        />
                        <MetricCard
                            label="Median views / day"
                            value="8,420"
                            detail="Collected Aug 7, 2026"
                            icon={Activity}
                            delta="3.1%"
                            trend="down"
                        />
                        <MetricCard
                            label="Unique channels"
                            value="18"
                            detail="Across first 2 pages"
                            icon={Layers3}
                            delta="Stable"
                            trend="flat"
                        />
                        <MetricCard
                            label="Opportunity"
                            value="78 / 100"
                            detail="Moderate confidence"
                            icon={Sparkles}
                            delta="Promising"
                            trend="up"
                        />
                    </div>
                </section>

                <section
                    id="status-and-feedback"
                    aria-labelledby="status-heading"
                    className="grid gap-6 xl:grid-cols-[1.35fr_1fr]"
                >
                    <Card className="overflow-hidden py-0">
                        <CardHeader className="border-b bg-muted/35 py-5">
                            <CardTitle id="status-heading">
                                Tables and status
                            </CardTitle>
                            <CardDescription>
                                Long multilingual titles wrap while numeric
                                values stay aligned.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Research query</TableHead>
                                        <TableHead>Market</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Observed views
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Score
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {fixtureRuns.map((run) => (
                                        <TableRow key={run.query}>
                                            <TableCell className="max-w-80 min-w-52 font-medium whitespace-normal">
                                                {run.query}
                                            </TableCell>
                                            <TableCell>
                                                <MarketBadge
                                                    market={run.market}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <RunStatus state={run.status} />
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs tabular-nums">
                                                {run.observedViews}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {run.score}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                    <div className="space-y-4">
                        <Card className="gap-4 py-5">
                            <CardHeader className="px-5">
                                <CardTitle>Markets</CardTitle>
                                <CardDescription>
                                    Sampling context stays visible near every
                                    result.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2 px-5">
                                <MarketBadge market="global_en" />
                                <MarketBadge market="ro_ro" />
                                <MarketBadge market="ru_ru" />
                            </CardContent>
                        </Card>
                        <Card className="gap-4 py-5">
                            <CardHeader className="px-5">
                                <CardTitle>Run lifecycle</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2 px-5">
                                <RunStatus state="queued" />
                                <RunStatus state="searching" />
                                <RunStatus state="partial" />
                                <RunStatus state="completed" />
                                <RunStatus state="failed" />
                            </CardContent>
                        </Card>
                        <PartialDataBanner />
                    </div>
                </section>

                <section aria-labelledby="states-heading">
                    <div className="mb-4">
                        <h2
                            id="states-heading"
                            className="text-lg font-semibold"
                        >
                            Data states
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Every research view can communicate waiting,
                            absence, recovery, and partial confidence.
                        </p>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-3">
                        <LoadingState />
                        <EmptyState />
                        <ErrorState />
                    </div>
                </section>
            </PageContainer>
        </>
    );
}

DesignSystem.layout = {
    breadcrumbs: [{ title: 'UI showcase', href: '/design-system' }],
};
