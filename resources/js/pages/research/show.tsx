import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    ExternalLink,
    FileSearch,
    ListChecks,
} from 'lucide-react';
import { useEffect } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { WorkspaceHandoff } from '@/features/integration/workspace-handoff';
import { ResearchAnalysisSection } from '@/features/research/analysis/research-analysis';
import { ResearchDecisionSummary } from '@/features/research/decision-summary';
import { ResearchEvidenceInspection } from '@/features/research/evidence-inspection';
import { ProvenancePanel } from '@/features/research/provenance-panel';
import { ResearchActions } from '@/features/research/research-actions';
import { ResearchEvidenceProfile } from '@/features/research/research-evidence-profile';
import { OpportunityScoreSection } from '@/features/research/scoring/opportunity-score-section';
import { ProfitabilityFitSection } from '@/features/research/scoring/profitability-fit-section';
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';
import { edit as editYouTube } from '@/routes/youtube';
import type { Auth, LibraryContext, QuotaSummary, ResearchRun } from '@/types';

type PageProps = {
    auth: Auth;
    run: ResearchRun;
    youtubeQuota: QuotaSummary | null;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    returnTo: string | null;
};

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function formatFilter(value: string | null) {
    if (!value || value === 'any') {
        return 'Any';
    }

    return value.replaceAll('_', ' ').replace('viewCount', 'View count');
}

export default function ResearchRunShow({
    run,
    library,
    workspaces,
    returnTo,
}: PageProps) {
    const { auth, youtubeQuota } = usePage<PageProps>().props;
    const { start, stop } = usePoll(
        2000,
        { only: ['run', 'youtubeQuota'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (run.is_active) {
            start();
        } else {
            stop();
        }

        return () => stop();
    }, [run.is_active, start, stop]);

    const searchBucket = youtubeQuota?.buckets.find(
        (bucket) =>
            bucket.bucket === 'search' || bucket.bucket === 'search.list',
    );

    return (
        <>
            <Head title={`${run.query_text} research`} />
            <PageContainer>
                <PageHeader
                    eyebrow={`Research attempt ${run.attempt_number}`}
                    title={run.query_text}
                    description="A decision view over this immutable stored sample. Viewing and inspecting it does not call YouTube."
                    actions={
                        <>
                            {returnTo && (
                                <Button variant="outline" asChild>
                                    <Link href={returnTo}>
                                        <ArrowLeft aria-hidden="true" /> Back to
                                        source
                                    </Link>
                                </Button>
                            )}
                            <ResearchActions
                                library={library}
                                runPublicId={run.public_id}
                                queryText={run.query_text}
                            />
                        </>
                    }
                />
                <AnalyticsGlossary page="research" />

                <div className="flex flex-wrap items-center gap-2">
                    <MarketBadge market={run.market.key} />
                    <Badge variant="outline">
                        Attempt {run.attempt_number}
                    </Badge>
                    {run.is_active && (
                        <span
                            className="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            role="status"
                        >
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60" />
                                <span className="relative inline-flex size-2 rounded-full bg-primary" />
                            </span>
                            Live updates every 2 seconds
                        </span>
                    )}
                </div>

                <ResearchDecisionSummary
                    summary={run.decision_summary}
                    score={run.score}
                    timezone={auth.user.timezone}
                    runPublicId={run.public_id}
                />

                {run.error && (
                    <Alert
                        variant="destructive"
                        className="border-destructive/40 bg-destructive/8"
                    >
                        <AlertTriangle aria-hidden="true" />
                        <AlertTitle>{run.error.title}</AlertTitle>
                        <AlertDescription>
                            <p>{run.error.message}</p>
                            <p>{run.error.guidance}</p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {run.error.action === 'settings' && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={editYouTube()}>
                                            Open YouTube settings
                                            <ExternalLink aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                                {run.error.action === 'new_search' && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={create()}>
                                            Adjust filters
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <OpportunityScoreSection
                    score={run.score}
                    status={run.status}
                    timezone={auth.user.timezone}
                    collectedAt={
                        run.analysis?.summary.latest_collected_at ?? null
                    }
                />

                <ProfitabilityFitSection
                    fit={run.profitability_fit}
                    status={run.status}
                />

                <ResearchEvidenceProfile profile={run.evidence_profile} />

                <ResearchEvidenceInspection
                    inspection={run.evidence_inspection}
                    runPublicId={run.public_id}
                    timezone={auth.user.timezone}
                />

                <ResearchAnalysisSection
                    analysis={run.analysis}
                    status={run.status}
                    timezone={auth.user.timezone}
                    library={library}
                    researchRunPublicId={run.public_id}
                    workspaces={workspaces}
                />

                <ProvenancePanel
                    provenance={run.provenance}
                    timezone={auth.user.timezone}
                    queryText={run.query_text}
                    marketName={run.market.name}
                    parameters={run.parameters}
                    formulaVersion={run.score?.formula_version ?? null}
                />

                <Card id="research-workspace-handoff">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Workspace handoff
                        </CardTitle>
                        <CardDescription>
                            Link this immutable Research run as evidence without
                            copying its metric payload.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WorkspaceHandoff
                            workspaces={workspaces}
                            targetType="research_run"
                            targetReference={run.public_id}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                    <ListChecks
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <CardTitle>Collected candidates</CardTitle>
                                    <CardDescription className="mt-1">
                                        A live preview of persisted search
                                        results, not final analysis metrics.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {run.collection?.sample_results.length ? (
                                <ol className="divide-y">
                                    {run.collection.sample_results.map(
                                        (result) => (
                                            <li
                                                key={result.provider_video_id}
                                                className="flex gap-3 py-4 first:pt-0 last:pb-0"
                                            >
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-semibold tabular-nums">
                                                    {result.result_rank}
                                                </span>
                                                <div className="min-w-0">
                                                    <p
                                                        className="line-clamp-2 text-sm font-medium"
                                                        title={result.title}
                                                    >
                                                        {result.title}
                                                    </p>
                                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                                        Channel{' '}
                                                        {
                                                            result.provider_channel_id
                                                        }{' '}
                                                        - Published{' '}
                                                        {formatTimestamp(
                                                            result.published_at,
                                                            auth.user.timezone,
                                                        )}
                                                    </p>
                                                </div>
                                            </li>
                                        ),
                                    )}
                                </ol>
                            ) : (
                                <div className="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                                    <FileSearch
                                        className="size-7 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p className="mt-3 text-sm font-medium">
                                        {run.status === 'failed'
                                            ? 'No candidates were saved'
                                            : 'Waiting for the first page'}
                                    </p>
                                    <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                                        {run.status === 'failed'
                                            ? 'The run record and safe error details remain available for retry.'
                                            : 'The local queue worker will persist candidates here as YouTube pages return.'}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Collection details
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-3 text-sm">
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Outcome
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {run.decision_summary?.lifecycle
                                                .label ?? 'Preparing'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Persisted progress
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {run.progress_percent}%
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Collected / enriched
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {run.collected_result_count} /{' '}
                                            {run.enriched_result_count}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Pages saved
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {run.collection?.pages_collected ??
                                                0}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Order
                                        </dt>
                                        <dd className="text-right font-medium capitalize">
                                            {formatFilter(
                                                run.parameters.search_order,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Duration
                                        </dt>
                                        <dd className="text-right font-medium capitalize">
                                            {formatFilter(
                                                run.parameters.video_duration,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Created
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {formatTimestamp(
                                                run.created_at,
                                                auth.user.timezone,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Search finished
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {formatTimestamp(
                                                run.search_completed_at,
                                                auth.user.timezone,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        <Alert className="border-info/35 bg-info/8 text-info-foreground">
                            {searchBucket?.exhausted ? (
                                <AlertTriangle aria-hidden="true" />
                            ) : (
                                <CalendarClock aria-hidden="true" />
                            )}
                            <AlertTitle>
                                {searchBucket
                                    ? `${searchBucket.remaining} search calls left`
                                    : 'Quota estimate unavailable'}
                            </AlertTitle>
                            <AlertDescription className="text-current/80">
                                {youtubeQuota
                                    ? `NisheTube estimate resets ${formatTimestamp(youtubeQuota.reset_at, auth.user.timezone)}. Google Cloud Console is authoritative.`
                                    : 'Review YouTube settings before starting another run.'}
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>
            </PageContainer>
        </>
    );
}

ResearchRunShow.layout = {
    breadcrumbs: [
        { title: 'Search', href: create() },
        { title: 'Live run', href: show('current') },
    ],
};
