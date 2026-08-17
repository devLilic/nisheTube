import { Link } from '@inertiajs/react';
import { CircleDollarSign, LoaderCircle, Search, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';
import type { DashboardData } from '@/types';

export function DecisionCockpit({ dashboard }: { dashboard: DashboardData }) {
    const best = dashboard.best_opportunity;

    if (!best) {
        const active = dashboard.counts.active_runs > 0;

        return (
            <section aria-labelledby="decision-cockpit-title">
                <Card className="border-primary/20 bg-primary/[0.035]">
                    <CardHeader>
                        <CardTitle id="decision-cockpit-title">
                            Decision cockpit
                        </CardTitle>
                        <CardDescription>
                            {active
                                ? 'Research is still collecting stored evidence.'
                                : 'No scored decision is available yet.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center justify-between gap-4">
                        <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                            {active
                                ? 'Wait for the active run to finish. The dashboard refreshes from local records and does not call YouTube.'
                                : 'Start a research run to create an immutable observed-activity snapshot and its decision evidence.'}
                        </p>
                        {active ? (
                            <Badge variant="outline">
                                <LoaderCircle
                                    className="size-3 animate-spin"
                                    aria-hidden="true"
                                />{' '}
                                Evidence pending
                            </Badge>
                        ) : (
                            <Button asChild>
                                <Link href={create()}>
                                    <Search aria-hidden="true" /> Start research
                                </Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </section>
        );
    }

    return (
        <section aria-labelledby="decision-cockpit-title">
            <Card className="border-primary/25 bg-gradient-to-br from-primary/[0.08] via-card to-card">
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="decision-cockpit-title">
                                Decision cockpit
                            </CardTitle>
                            <CardDescription className="mt-1">
                                Your strongest current-version opportunity from
                                the last {dashboard.opportunity_window_days}{' '}
                                days.
                            </CardDescription>
                        </div>
                        <Badge variant="outline">Observed evidence</Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-lg font-semibold">
                                {best.query_text}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Opportunity {best.overall_score.toFixed(1)} /
                                100 · Confidence{' '}
                                {best.confidence_score.toFixed(1)} / 100
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={show(best.public_id)}>
                                Review evidence <Sparkles aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                    {best.profitability_fit ? (
                        <div className="rounded-lg border border-primary/15 bg-background/60 p-4">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <CircleDollarSign
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />{' '}
                                Estimated profitability fit
                            </div>
                            <p className="mt-2 text-2xl font-semibold tabular-nums">
                                {best.profitability_fit.fit_score.toFixed(1)}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    / 100
                                </span>
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Directional fit from frozen opportunity
                                evidence. It does not measure revenue, RPM,
                                costs, or profit.
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            Estimated profitability fit is unavailable for this
                            legacy scored result. Its original records were not
                            changed.
                        </div>
                    )}
                </CardContent>
            </Card>
        </section>
    );
}
