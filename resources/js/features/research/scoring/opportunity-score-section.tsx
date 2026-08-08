import type { LucideIcon } from 'lucide-react';
import {
    AlertCircle,
    AlertTriangle,
    BarChart3,
    Clock3,
    Layers3,
    LoaderCircle,
    RadioTower,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { StatePanel } from '@/components/data-state';
import { ScoreGauge } from '@/components/score-gauge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    OpportunityScore,
    OpportunityScoreComponent,
    ResearchRunStatus,
} from '@/types';

const componentIcons: Record<OpportunityScoreComponent['key'], LucideIcon> = {
    demand_momentum: TrendingUp,
    competition_opportunity: UsersRound,
    audience_reachability: RadioTower,
    content_freshness_gap: Clock3,
    creator_viability: Layers3,
};

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Unavailable';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function ComponentCard({
    component,
}: {
    component: OpportunityScoreComponent;
}) {
    const Icon = componentIcons[component.key];
    const scoreLabel = `${component.score.toFixed(1)} out of 100`;

    return (
        <Card className="gap-4 border-border/70 py-5 shadow-sm shadow-primary/5">
            <CardHeader className="gap-3 px-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Icon className="size-4" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <CardTitle className="text-base">
                                {component.label}
                            </CardTitle>
                            <CardDescription className="mt-0.5">
                                {component.weight_percent.toFixed(0)}% of the
                                overall score
                            </CardDescription>
                        </div>
                    </div>
                    <span className="shrink-0 text-xl font-semibold tabular-nums">
                        {component.score.toFixed(1)}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="space-y-3 px-5">
                <div
                    className="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-label={`${component.label}: ${scoreLabel}`}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={component.score}
                    aria-valuetext={scoreLabel}
                >
                    <div
                        className="h-full rounded-full bg-primary"
                        style={{ width: `${component.score}%` }}
                    />
                </div>
                <p className="text-sm leading-6 text-muted-foreground">
                    {component.explanation}
                </p>
            </CardContent>
        </Card>
    );
}

export function OpportunityScoreSection({
    score,
    status,
    timezone,
    collectedAt,
}: {
    score: OpportunityScore | null | undefined;
    status: ResearchRunStatus;
    timezone: string;
    collectedAt: string | null;
}) {
    if (!score) {
        if (!['completed', 'failed'].includes(status)) {
            return (
                <section aria-label="Opportunity score">
                    <StatePanel
                        title={
                            status === 'scoring'
                                ? 'Opportunity score is being calculated'
                                : 'Opportunity score is waiting for metrics'
                        }
                        description={
                            status === 'scoring'
                                ? 'The deterministic scoring job is evaluating the saved snapshots. This page refreshes automatically.'
                                : 'A score will appear after candidate collection and metric enrichment finish.'
                        }
                        icon={LoaderCircle}
                    />
                </section>
            );
        }

        if (status === 'failed') {
            return (
                <section aria-label="Opportunity score">
                    <StatePanel
                        title="Opportunity score unavailable"
                        description="This run ended before a score could be persisted. The saved run can be retried from the page header."
                        icon={AlertCircle}
                        tone="danger"
                    />
                </section>
            );
        }

        return (
            <section aria-label="Opportunity score">
                <StatePanel
                    title="Not enough stored data to score"
                    description="This completed run has no persisted opportunity score. Try a new search with a larger result depth or review partial-data guidance."
                    icon={BarChart3}
                />
            </section>
        );
    }

    return (
        <section
            aria-labelledby="opportunity-score-title"
            className="space-y-6"
        >
            <Card className="overflow-hidden border-primary/25 bg-gradient-to-br from-primary/[0.07] via-card to-card py-0">
                <div className="grid lg:grid-cols-[minmax(0,1fr)_19rem]">
                    <div className="flex flex-col items-center gap-6 p-6 lg:p-8 xl:flex-row xl:items-start">
                        <ScoreGauge
                            score={score.overall_score}
                            label={score.overall_label}
                        />
                        <div className="min-w-0 flex-1 text-center xl:pt-3 xl:text-left">
                            <div className="flex flex-col items-center gap-3 xl:items-start">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                                        Observed niche opportunity
                                    </p>
                                    <h2
                                        id="opportunity-score-title"
                                        className="mt-2 text-2xl font-semibold tracking-tight"
                                    >
                                        {score.overall_label}
                                    </h2>
                                </div>
                                <ConfidenceBadge
                                    score={score.confidence_score}
                                    label={score.confidence_label}
                                />
                            </div>
                            <p className="mt-4 max-w-xl text-sm leading-6 text-muted-foreground">
                                A comparative decision aid built from this
                                run&apos;s stored video and channel snapshots.
                                It is not search volume, revenue potential, or a
                                guarantee of performance.
                            </p>
                        </div>
                    </div>

                    <div className="border-t border-primary/15 bg-background/45 p-6 lg:border-t-0 lg:border-l lg:p-8">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <Sparkles
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Formula context
                        </div>
                        <dl className="mt-5 space-y-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    Version
                                </dt>
                                <dd className="mt-1">
                                    <Badge
                                        variant="outline"
                                        className="font-mono"
                                    >
                                        {score.formula_version}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Scored sample
                                </dt>
                                <dd className="mt-1 font-semibold tabular-nums">
                                    {score.sample_size.toLocaleString('en-US')}{' '}
                                    videos
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Metrics collected
                                </dt>
                                <dd className="mt-1 font-semibold">
                                    {formatTimestamp(collectedAt, timezone)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Score calculated
                                </dt>
                                <dd className="mt-1 font-semibold">
                                    {formatTimestamp(
                                        score.calculated_at,
                                        timezone,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </Card>

            {score.warnings.length > 0 && (
                <Alert className="border-warning/40 bg-warning/10 text-warning-foreground">
                    <AlertTriangle aria-hidden="true" />
                    <AlertTitle>Confidence and interpretation notes</AlertTitle>
                    <AlertDescription className="text-warning-foreground/85">
                        <ul className="mt-1 list-disc space-y-1.5 pl-5">
                            {score.warnings.map((warning) => (
                                <li key={warning.code}>{warning.message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            <div>
                <div className="mb-4">
                    <h3 className="text-lg font-semibold">Why this score</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Each stored component is shown with its fixed v1 weight
                        and the evidence used by the backend engine.
                    </p>
                </div>
                <div className="grid gap-4 xl:grid-cols-3">
                    {score.components.map((component) => (
                        <ComponentCard
                            key={component.key}
                            component={component}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}
