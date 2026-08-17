import { Form, Head, Link, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, RefreshCw, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { CandidateList } from '@/features/discovery/candidate-list';
import { DiscoveryProgress } from '@/features/discovery/discovery-progress';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import type {
    DiscoveryCandidateTable,
    DiscoveryRun,
    LibraryContext,
    QuotaSummary,
} from '@/types';

type PageProps = {
    run: DiscoveryRun;
    youtubeQuota: QuotaSummary | null;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    candidate_table: DiscoveryCandidateTable;
};

export default function DiscoveryRunShow({
    run,
    library,
    workspaces,
    candidate_table,
}: PageProps) {
    const { youtubeQuota } = usePage<PageProps>().props;
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
    const validationBlocked = searchBucket?.remaining === 0;

    return (
        <>
            <Head title="Discovery results" />
            <PageContainer>
                <PageHeader
                    eyebrow="Discovery run"
                    title="Candidate niche themes"
                    description="Deterministic breakout evidence from your stored YouTube research samples. Scores describe observed returned videos, not search volume."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/discover">
                                    <ArrowLeft aria-hidden="true" /> New
                                    discovery
                                </Link>
                            </Button>
                            {run.can_retry && (
                                <Form
                                    action={`/discover/runs/${run.public_id}/retry`}
                                    method="post"
                                    disableWhileProcessing
                                >
                                    {({ processing }) => (
                                        <Button disabled={processing}>
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <RefreshCw />
                                            )}
                                            Retry analysis
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </>
                    }
                />
                <AnalyticsGlossary page="discovery" />

                <div className="flex flex-wrap items-center gap-2">
                    <MarketBadge market={run.market.key} />
                    <Badge variant="outline">{run.status}</Badge>
                    <Badge variant="secondary">{run.seed_count} seeds</Badge>
                    <Badge variant="secondary">
                        {run.candidate_count} candidates
                    </Badge>
                    {run.is_active && (
                        <span
                            className="text-xs text-muted-foreground"
                            role="status"
                        >
                            Live updates every 2 seconds
                        </span>
                    )}
                </div>

                <DiscoveryProgress
                    status={run.status}
                    progress={run.progress_percent}
                />

                {run.error && (
                    <Alert
                        variant="destructive"
                        className="border-destructive/40 bg-destructive/8"
                    >
                        <AlertTriangle aria-hidden="true" />
                        <AlertTitle>
                            Discovery analysis could not finish
                        </AlertTitle>
                        <AlertDescription>
                            {run.error.message} Your seed links and stored
                            snapshots are safe; retrying does not consume search
                            quota.
                        </AlertDescription>
                    </Alert>
                )}

                {run.partial_warnings.map((warning) => (
                    <Alert
                        key={warning}
                        className="border-warning/40 bg-warning/10"
                    >
                        <AlertTriangle aria-hidden="true" />
                        <AlertTitle>Partial sample evidence</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                <Card className="py-5 shadow-none">
                    <CardHeader className="pb-0">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Sparkles className="size-4 text-primary" />{' '}
                            Evidence plan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm md:grid-cols-3">
                        <div>
                            <p className="font-medium">
                                {run.parameters.sample_per_seed} videos per seed
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Deterministic stored sampling
                            </p>
                        </div>
                        <div>
                            <p className="font-medium">
                                Up to {run.parameters.candidate_limit}{' '}
                                candidates
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Ranked breakout clusters
                            </p>
                        </div>
                        <div>
                            <p className="font-medium">
                                {run.parameters.formula_version}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Frozen formula version
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {run.is_active ? (
                    <Card aria-busy="true">
                        <CardContent className="flex min-h-44 flex-col items-center justify-center text-center">
                            <Spinner className="size-6" />
                            <p className="mt-4 font-medium">
                                Analyzing stored evidence
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Breakout detection, topic extraction, and
                                clustering are running in the local queue.
                            </p>
                        </CardContent>
                    </Card>
                ) : run.status === 'completed' ? (
                    <CandidateList
                        table={candidate_table}
                        validationBlocked={validationBlocked}
                        library={library}
                        workspaces={workspaces}
                        discoveryRunPublicId={run.public_id}
                    />
                ) : null}
            </PageContainer>
        </>
    );
}

DiscoveryRunShow.layout = {
    breadcrumbs: [
        { title: 'Discover', href: '/discover' },
        { title: 'Run', href: '#' },
    ],
};
