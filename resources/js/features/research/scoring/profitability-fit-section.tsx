import { AlertTriangle, CircleDollarSign, LoaderCircle } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProfitabilityFit, ResearchRunStatus } from '@/types';

export function ProfitabilityFitSection({
    fit,
    status,
}: {
    fit: ProfitabilityFit | null | undefined;
    status: ResearchRunStatus;
}) {
    if (!fit) {
        return (
            <section aria-label="Estimated profitability fit">
                <StatePanel
                    title={
                        status === 'completed'
                            ? 'Estimated profitability fit unavailable for this legacy result'
                            : 'Estimated profitability fit is waiting for stored scoring evidence'
                    }
                    description={
                        status === 'completed'
                            ? 'This historical result was completed before profitability-fit estimates were stored. Its original records have not been changed.'
                            : 'The estimate is calculated only from persisted opportunity evidence; it never calls YouTube while you view this page.'
                    }
                    icon={
                        status === 'completed' ? CircleDollarSign : LoaderCircle
                    }
                />
            </section>
        );
    }

    return (
        <section aria-labelledby="profitability-fit-title">
            <Card className="border-primary/20">
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="profitability-fit-title">
                                Estimated profitability fit
                            </CardTitle>
                            <CardDescription className="mt-1">
                                A directional fit signal, not revenue or profit.
                            </CardDescription>
                        </div>
                        <Badge variant="outline" className="font-mono">
                            {fit.formula_version}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg bg-muted/60 p-4">
                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                Fit estimate
                            </p>
                            <p className="mt-1 text-3xl font-semibold tabular-nums">
                                {fit.fit_score.toFixed(1)}
                                <span className="text-base text-muted-foreground">
                                    {' '}
                                    / 100
                                </span>
                            </p>
                        </div>
                        <div className="rounded-lg bg-muted/60 p-4">
                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                Evidence confidence
                            </p>
                            <p className="mt-1 text-3xl font-semibold tabular-nums">
                                {fit.confidence_score.toFixed(1)}
                                <span className="text-base text-muted-foreground">
                                    {' '}
                                    / 100
                                </span>
                            </p>
                        </div>
                    </div>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {fit.explanations.fit}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Derived from frozen{' '}
                        <span className="font-mono">
                            {fit.source_formula_version}
                        </span>{' '}
                        evidence. Calculated{' '}
                        {new Intl.DateTimeFormat('en', {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        }).format(new Date(fit.calculated_at))}
                        .
                    </p>
                    <Alert className="border-warning/40 bg-warning/10 text-warning-foreground">
                        <AlertTriangle aria-hidden="true" />
                        <AlertTitle>Interpretation notes</AlertTitle>
                        <AlertDescription className="text-warning-foreground/85">
                            <ul className="mt-1 list-disc space-y-1 pl-5">
                                {fit.warnings.map((warning) => (
                                    <li key={warning.code}>
                                        {warning.message}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        </section>
    );
}
