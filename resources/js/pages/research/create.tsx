import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3 } from 'lucide-react';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { RunStatus } from '@/components/run-status';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchForm } from '@/features/research/search-form';
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';
import type { MarketKey, ResearchMarketOption, ResearchRun } from '@/types';

type PageProps = {
    markets: ResearchMarketOption[];
    defaults: {
        market_key: MarketKey;
        result_depth: 25 | 50 | 100 | 200;
    };
    recent_runs: ResearchRun[];
};

export default function CreateResearch({
    markets,
    defaults,
    recent_runs: recentRuns,
}: PageProps) {
    return (
        <>
            <Head title="Search" />
            <PageContainer>
                <PageHeader
                    eyebrow="Observed YouTube demand"
                    title="Start a research run"
                    description="Choose a market and sample depth, then watch NisheTube collect a durable snapshot in the background."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
                    <SearchForm markets={markets} defaults={defaults} />

                    <Card className="xl:sticky xl:top-6">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-base">
                                    Recent runs
                                </CardTitle>
                                <Clock3
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            {recentRuns.length === 0 ? (
                                <div className="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed px-5 text-center">
                                    <Clock3
                                        className="size-6 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p className="mt-3 text-sm font-medium">
                                        No research runs yet
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        Complete the form to create your first
                                        durable snapshot.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {recentRuns.map((run) => (
                                        <Link
                                            key={run.public_id}
                                            href={show(run.public_id)}
                                            className="group block py-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium group-hover:text-primary">
                                                        {run.query_text}
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        <MarketBadge
                                                            market={
                                                                run.market.key
                                                            }
                                                        />
                                                        <RunStatus
                                                            state={run.status}
                                                        />
                                                    </div>
                                                </div>
                                                <ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
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

CreateResearch.layout = {
    breadcrumbs: [{ title: 'Search', href: create() }],
};
