import { Form, Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    ExternalLink,
    FileSearch,
    ListChecks,
    RefreshCw,
} from 'lucide-react';
import { useEffect } from 'react';
import ResearchRunController from '@/actions/App/Http/Controllers/Research/ResearchRunController';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { PartialDataBanner } from '@/components/partial-data-banner';
import { RunStatus } from '@/components/run-status';
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
import { Spinner } from '@/components/ui/spinner';
import { ResearchAnalysisSection } from '@/features/research/analysis/research-analysis';
import { RunProgress } from '@/features/research/run-progress';
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';
import { edit as editYouTube } from '@/routes/youtube';
import type { Auth, QuotaSummary, ResearchRun } from '@/types';

type PageProps = {
    auth: Auth;
    run: ResearchRun;
    youtubeQuota: QuotaSummary | null;
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

export default function ResearchRunShow({ run }: PageProps) {
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
    const progressLabel = `${run.progress_percent}% complete`;

    return (
        <>
            <Head title={`${run.query_text} research`} />
            <PageContainer>
                <PageHeader
                    eyebrow={`Research attempt ${run.attempt_number}`}
                    title={run.query_text}
                    description="Live collection status from the persisted run. Counts and warnings update as the local queue worker progresses."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={create()}>
                                    <ArrowLeft aria-hidden="true" />
                                    New search
                                </Link>
                            </Button>
                            {run.can_retry && (
                                <Form
                                    {...ResearchRunController.retry.form(
                                        run.public_id,
                                    )}
                                    disableWhileProcessing
                                >
                                    {({ processing }) => (
                                        <Button disabled={processing}>
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <RefreshCw aria-hidden="true" />
                                            )}
                                            {processing
                                                ? 'Queuing retry...'
                                                : 'Retry run'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <MarketBadge market={run.market.key} />
                    <RunStatus state={run.status} />
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

                {run.collection_warnings.map((warning) => (
                    <PartialDataBanner
                        key={warning}
                        title="Partial collection saved"
                        description={warning}
                    />
                ))}

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

                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <CardTitle>Run progress</CardTitle>
                                <CardDescription className="mt-1">
                                    Real persisted progress; final analysis
                                    remains hidden until its stage is ready.
                                </CardDescription>
                            </div>
                            <span className="text-2xl font-semibold tabular-nums">
                                {run.progress_percent}%
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div>
                            <div
                                className="h-2.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-label="Research run progress"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={run.progress_percent}
                                aria-valuetext={progressLabel}
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-[width] duration-500"
                                    style={{
                                        width: `${run.progress_percent}%`,
                                    }}
                                />
                            </div>
                            <div className="mt-2 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                                <span>
                                    {run.collected_result_count} of{' '}
                                    {run.requested_result_count} candidates
                                    collected
                                </span>
                                <span>
                                    {run.enriched_result_count} enriched
                                </span>
                            </div>
                        </div>
                        <RunProgress
                            status={run.status}
                            startedAt={run.started_at}
                            searchCompletedAt={run.search_completed_at}
                        />
                    </CardContent>
                </Card>

                <ResearchAnalysisSection
                    analysis={run.analysis}
                    status={run.status}
                    timezone={auth.user.timezone}
                />

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

                        {run.status === 'completed' && (
                            <Alert className="border-success/35 bg-success/8 text-success-foreground">
                                <CheckCircle2 aria-hidden="true" />
                                <AlertTitle>Snapshot complete</AlertTitle>
                                <AlertDescription className="text-current/80">
                                    Completed{' '}
                                    {formatTimestamp(
                                        run.completed_at,
                                        auth.user.timezone,
                                    )}
                                    .
                                </AlertDescription>
                            </Alert>
                        )}
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
