import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3, Compass } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DiscoveryForm } from '@/features/discovery/discovery-form';
import type {
    DiscoveryRun,
    DiscoverySampleRun,
    MarketKey,
    ResearchMarketOption,
} from '@/types';

type PageProps = {
    markets: ResearchMarketOption[];
    default_market_key: MarketKey;
    sample_runs: DiscoverySampleRun[];
    recent_runs: DiscoveryRun[];
};

export default function DiscoveryIndex({
    markets,
    default_market_key: defaultMarketKey,
    sample_runs: sampleRuns,
    recent_runs: recentRuns,
}: PageProps) {
    const [runMarket, setRunMarket] = useState<MarketKey | 'all'>('all');
    const filteredRuns = useMemo(
        () =>
            recentRuns.filter(
                (run) => runMarket === 'all' || run.market.key === runMarket,
            ),
        [recentRuns, runMarket],
    );

    return (
        <>
            <Head title="Discover" />
            <PageContainer>
                <PageHeader
                    eyebrow="Observed breakout signals"
                    title="Discover promising niche themes"
                    description="Analyze completed market samples for recurring breakout topics, then validate the strongest candidates with a full research run."
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
                    <DiscoveryForm
                        markets={markets}
                        defaultMarketKey={defaultMarketKey}
                        sampleRuns={sampleRuns}
                    />
                    <Card className="xl:sticky xl:top-6">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-base">
                                    Recent discovery runs
                                </CardTitle>
                                <Clock3 className="size-4 text-muted-foreground" />
                            </div>
                            <label
                                htmlFor="recent-run-market"
                                className="sr-only"
                            >
                                Filter recent discovery runs by market
                            </label>
                            <select
                                id="recent-run-market"
                                value={runMarket}
                                onChange={(event) =>
                                    setRunMarket(
                                        event.target.value as MarketKey | 'all',
                                    )
                                }
                                className="mt-3 h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">All markets</option>
                                {markets.map((market) => (
                                    <option key={market.key} value={market.key}>
                                        {market.name}
                                    </option>
                                ))}
                            </select>
                        </CardHeader>
                        <CardContent>
                            {filteredRuns.length === 0 ? (
                                <div className="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed px-5 text-center">
                                    <Compass className="size-6 text-muted-foreground" />
                                    <p className="mt-3 text-sm font-medium">
                                        No discovery runs yet
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        Select a stored sample to generate your
                                        first candidate set.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {filteredRuns.map((run) => (
                                        <Link
                                            key={run.public_id}
                                            href={`/discover/runs/${run.public_id}`}
                                            className="group block py-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 space-y-2">
                                                    <div className="flex flex-wrap gap-2">
                                                        <MarketBadge
                                                            market={
                                                                run.market.key
                                                            }
                                                        />
                                                        <Badge variant="outline">
                                                            {run.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {run.seed_count} seeds ·{' '}
                                                        {run.candidate_count}{' '}
                                                        candidates
                                                    </p>
                                                </div>
                                                <ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground group-hover:text-primary" />
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageContainer>
        </>
    );
}

DiscoveryIndex.layout = {
    breadcrumbs: [{ title: 'Discover', href: '/discover' }],
};
