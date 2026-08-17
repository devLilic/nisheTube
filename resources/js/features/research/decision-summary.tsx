import { Form, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    CheckCircle2,
    Clock3,
    Database,
    RefreshCw,
    Search,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';
import ResearchRunController from '@/actions/App/Http/Controllers/Research/ResearchRunController';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create } from '@/routes/research';
import type {
    OpportunityScore,
    ResearchDecisionSummary as DecisionSummary,
} from '@/types';

const lifecycleTone: Record<DecisionSummary['lifecycle']['key'], string> = {
    active: 'border-info/35 bg-info/10 text-info-foreground',
    complete_data: 'border-success/35 bg-success/10 text-success-foreground',
    partial_data: 'border-warning/40 bg-warning/10 text-warning-foreground',
    reduced_confidence:
        'border-warning/40 bg-warning/10 text-warning-foreground',
    failed_with_partial:
        'border-destructive/35 bg-destructive/10 text-destructive',
    failed: 'border-destructive/35 bg-destructive/10 text-destructive',
};

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Not observed yet';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function NextAction({
    summary,
    runPublicId,
}: {
    summary: DecisionSummary;
    runPublicId: string;
}) {
    const action = summary.next_action;

    return (
        <div className="rounded-xl border border-primary/20 bg-primary/[0.06] p-4">
            <p className="text-xs font-semibold tracking-[0.14em] text-primary uppercase">
                Recommended next action
            </p>
            <p className="mt-2 font-semibold">{action.label}</p>
            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                {action.description}
            </p>
            <div className="mt-4">
                {action.kind === 'retry' && (
                    <Form
                        {...ResearchRunController.retry.form(runPublicId)}
                        disableWhileProcessing
                    >
                        {({ processing }) => (
                            <Button disabled={processing}>
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <RefreshCw aria-hidden="true" />
                                )}
                                {processing ? 'Queuing retry...' : action.label}
                            </Button>
                        )}
                    </Form>
                )}
                {action.kind === 'new_search' && (
                    <Button asChild>
                        <Link href={create()}>
                            <Search aria-hidden="true" />
                            {action.label}
                        </Link>
                    </Button>
                )}
                {action.kind === 'review_evidence' && (
                    <Button asChild>
                        <a href="#principal-evidence">
                            <ArrowDown aria-hidden="true" />
                            {action.label}
                        </a>
                    </Button>
                )}
                {action.kind === 'wait' && (
                    <Button disabled>
                        <Clock3 aria-hidden="true" />
                        Waiting for terminal result
                    </Button>
                )}
            </div>
        </div>
    );
}

export function ResearchDecisionSummary({
    summary,
    score,
    timezone,
    runPublicId,
}: {
    summary: DecisionSummary | undefined;
    score: OpportunityScore | null | undefined;
    timezone: string;
    runPublicId: string;
}) {
    if (!summary) {
        return (
            <Card aria-busy="true">
                <CardHeader>
                    <CardTitle>Preparing decision summary</CardTitle>
                    <CardDescription>
                        Stored lifecycle and evidence context is loading.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const LifecycleIcon =
        summary.lifecycle.key === 'complete_data'
            ? CheckCircle2
            : summary.lifecycle.key === 'active'
              ? Sparkles
              : AlertTriangle;

    return (
        <section
            aria-labelledby="research-decision-title"
            className="space-y-5"
        >
            <Card className="overflow-hidden border-primary/25 py-0 shadow-sm shadow-primary/5">
                <div className="grid xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.65fr)]">
                    <div className="bg-gradient-to-br from-primary/[0.09] via-card to-card p-6 lg:p-8">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className={cn(
                                    'gap-1.5',
                                    lifecycleTone[summary.lifecycle.key],
                                )}
                            >
                                <LifecycleIcon
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {summary.lifecycle.label}
                            </Badge>
                            {score && (
                                <ConfidenceBadge
                                    score={score.confidence_score}
                                    label={score.confidence_label}
                                />
                            )}
                        </div>

                        <p className="mt-6 text-xs font-semibold tracking-[0.16em] text-primary uppercase">
                            Decision verdict
                        </p>
                        <div className="mt-2 flex flex-wrap items-end gap-x-5 gap-y-2">
                            <h2
                                id="research-decision-title"
                                className="max-w-3xl text-3xl font-semibold tracking-tight"
                            >
                                {summary.verdict}
                            </h2>
                            {score && (
                                <span
                                    className="text-3xl font-semibold text-primary tabular-nums"
                                    aria-label={`${score.overall_score.toFixed(1)} opportunity score out of 100`}
                                >
                                    {score.overall_score.toFixed(1)}
                                    <span className="text-base text-muted-foreground">
                                        /100
                                    </span>
                                </span>
                            )}
                        </div>
                        <p className="mt-4 max-w-3xl text-sm leading-6 text-muted-foreground">
                            {summary.interpretation}
                        </p>
                        <p className="mt-3 text-xs leading-5 text-muted-foreground">
                            {summary.lifecycle.description}
                        </p>

                        {summary.active_progress && (
                            <div className="mt-6 rounded-xl border bg-background/70 p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {summary.active_progress.stage}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {summary.active_progress.collected_count.toLocaleString(
                                                'en-US',
                                            )}{' '}
                                            of{' '}
                                            {summary.active_progress.requested_count.toLocaleString(
                                                'en-US',
                                            )}{' '}
                                            candidates collected ·{' '}
                                            {
                                                summary.active_progress
                                                    .warning_count
                                            }{' '}
                                            warnings
                                        </p>
                                    </div>
                                    <span className="text-2xl font-semibold tabular-nums">
                                        {summary.active_progress.percent}%
                                    </span>
                                </div>
                                <div
                                    className="mt-4 h-2.5 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    aria-label="Research run progress"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={
                                        summary.active_progress.percent
                                    }
                                    aria-valuetext={`${summary.active_progress.percent}% complete`}
                                >
                                    <div
                                        className="h-full rounded-full bg-primary transition-[width] duration-500"
                                        style={{
                                            width: `${summary.active_progress.percent}%`,
                                        }}
                                    />
                                </div>
                                <p className="mt-3 text-xs font-medium">
                                    {summary.active_progress.eta_label}
                                </p>
                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                    {summary.active_progress.eta_explanation}
                                </p>
                            </div>
                        )}
                    </div>

                    <CardContent className="space-y-5 border-t p-6 lg:p-8 xl:border-t-0 xl:border-l">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Scored sample
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {summary.sample_size.toLocaleString(
                                        'en-US',
                                    )}{' '}
                                    videos
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Stability
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {summary.stability.label}
                                </p>
                            </div>
                        </div>
                        <div className="rounded-lg bg-muted/55 p-3">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Clock3
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                {summary.observation.freshness_label}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Observed{' '}
                                {formatTimestamp(
                                    summary.observation.collected_at,
                                    timezone,
                                )}
                            </p>
                        </div>
                        <p className="text-xs leading-5 text-muted-foreground">
                            {summary.stability.description}
                        </p>
                        <NextAction
                            summary={summary}
                            runPublicId={runPublicId}
                        />
                    </CardContent>
                </div>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <span className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Database className="size-4" aria-hidden="true" />
                        </span>
                        <div>
                            <CardTitle className="text-base">
                                Field-level completeness
                            </CardTitle>
                            <CardDescription className="mt-1">
                                Available values over the exact relevant stored
                                denominator. Missing values remain null.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {summary.completeness.map((field) => (
                        <div key={field.key} className="rounded-xl border p-3">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="font-medium">
                                    {field.label}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {field.available}/{field.total}
                                </span>
                            </div>
                            <div
                                className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-label={`${field.label} completeness`}
                                aria-valuemin={0}
                                aria-valuemax={field.total || 1}
                                aria-valuenow={field.available}
                                aria-valuetext={
                                    field.percent === null
                                        ? 'No denominator available'
                                        : `${field.percent}% complete, ${field.available} of ${field.total}`
                                }
                            >
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        field.state === 'complete'
                                            ? 'bg-success'
                                            : field.state === 'partial'
                                              ? 'bg-warning'
                                              : 'bg-muted-foreground/30',
                                    )}
                                    style={{ width: `${field.percent ?? 0}%` }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground capitalize">
                                {field.state === 'unavailable'
                                    ? 'Not available yet'
                                    : `${field.percent}% ${field.state}`}
                            </p>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                <Card id="principal-evidence">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Principal evidence
                        </CardTitle>
                        <CardDescription>
                            Up to five deterministic signals selected from the
                            stored score and sample.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {summary.principal_evidence.length > 0 ? (
                            <ol className="grid gap-3 sm:grid-cols-2">
                                {summary.principal_evidence.map(
                                    (evidence, index) => (
                                        <li
                                            key={`${evidence.label}-${index}`}
                                            className="rounded-xl border p-4"
                                        >
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {evidence.label}
                                            </p>
                                            <p className="mt-1 text-lg font-semibold tabular-nums">
                                                {evidence.value}
                                            </p>
                                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                                {evidence.explanation}
                                            </p>
                                        </li>
                                    ),
                                )}
                            </ol>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Principal evidence will appear after enriched
                                metrics or a score is stored.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldAlert
                                className="size-4 text-warning-foreground"
                                aria-hidden="true"
                            />
                            <CardTitle className="text-base">
                                Decision risks
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-3 text-sm leading-6">
                            {summary.risks.map((risk) => (
                                <li key={risk} className="flex gap-2">
                                    <AlertTriangle
                                        className="mt-1 size-3.5 shrink-0 text-warning-foreground"
                                        aria-hidden="true"
                                    />
                                    <span>{risk}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}
