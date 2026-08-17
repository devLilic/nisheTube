import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Database,
    GitCompareArrows,
    LoaderCircle,
    RefreshCw,
    Settings,
} from 'lucide-react';
import { useEffect } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { PartialDataBanner } from '@/components/partial-data-banner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AnalyzerCurationPanel } from '@/features/analyzer/analyzer-curation';
import { AnalyzerProfileTabs } from '@/features/analyzer/analyzer-profile-tabs';
import { AnalyzerStatus } from '@/features/analyzer/analyzer-status';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import type { AnalyzerRun, Auth, LibraryContext } from '@/types';

export default function AnalyzerShow({
    run,
    auth,
    library,
    workspaces,
}: {
    run: AnalyzerRun;
    auth: Auth;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
}) {
    useEffect(() => {
        if (
            !run.is_active &&
            !run.comments?.is_active &&
            !run.thumbnail_analysis?.is_active
        ) {
            return;
        }

        const timer = window.setInterval(
            () =>
                router.reload({
                    only: ['run', 'youtubeQuota'],
                }),
            2000,
        );

        return () => window.clearInterval(timer);
    }, [
        run.is_active,
        run.comments?.is_active,
        run.thumbnail_analysis?.is_active,
    ]);

    const refresh = (mode: 'allow_cache' | 'force_refresh') =>
        router.post(`/analyzer/runs/${run.public_id}/refresh`, { mode });
    const returnsToExplore = run.origin.return_url?.startsWith('/explore');

    return (
        <>
            <Head title={`Analyzer · ${run.display_label}`} />
            <PageContainer>
                <PageHeader
                    title="Analyzer profile"
                    compact
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {run.channel && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/analyzer/compare?before=${run.public_id}`}
                                    >
                                        <GitCompareArrows /> Compare channel
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link
                                    href={run.origin.return_url ?? '/analyzer'}
                                >
                                    <ArrowLeft />{' '}
                                    {returnsToExplore
                                        ? 'Return to Explore'
                                        : run.origin.return_url
                                          ? 'Back to source'
                                          : 'New analysis'}
                                </Link>
                            </Button>
                            {run.can_refresh && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => refresh('allow_cache')}
                                    >
                                        <RefreshCw /> Refresh
                                    </Button>
                                    <Button
                                        onClick={() => refresh('force_refresh')}
                                    >
                                        Force Refresh
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />
                <AnalyticsGlossary page="analyzer" />

                <div className="flex flex-wrap items-center gap-2">
                    <AnalyzerStatus status={run.status} />
                    <Badge variant="outline">
                        Attempt {run.attempt_number}
                    </Badge>
                    <Badge variant="outline" className="capitalize">
                        {run.cache_policy.replaceAll('_', ' ')}
                    </Badge>
                    {run.origin.kind === 'search' && (
                        <Badge variant="outline">Opened from Search</Badge>
                    )}
                </div>

                {run.warnings.map((warning) => (
                    <PartialDataBanner
                        key={warning}
                        title="Partial or cached source context"
                        description={warning}
                    />
                ))}

                {run.error && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>{run.error.title}</AlertTitle>
                        <AlertDescription>
                            <p>{run.error.message}</p>
                            <p>{run.error.guidance}</p>
                            {run.error.action === 'settings' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    asChild
                                >
                                    <Link href="/settings/youtube">
                                        <Settings /> Open YouTube settings
                                    </Link>
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {run.is_active && (
                    <Card aria-busy="true">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle>Analysis progress</CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {run.status_label}. Live persisted
                                        updates refresh every two seconds.
                                    </p>
                                </div>
                                <LoaderCircle className="size-5 animate-spin text-primary" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                role="progressbar"
                                aria-label="Analyzer progress"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={run.progress_percent}
                                className="h-2.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-[width]"
                                    style={{
                                        width: `${run.progress_percent}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-2 text-right text-sm font-medium tabular-nums">
                                {run.progress_percent}%
                            </p>
                        </CardContent>
                    </Card>
                )}

                <AnalyzerProfileTabs
                    run={run}
                    timezone={auth.user.timezone}
                    library={library}
                    workspaces={workspaces}
                />

                {run.channel && (
                    <section
                        aria-label="Analyzer actions"
                        className="sticky bottom-3 z-10 rounded-xl border bg-background/95 p-3 shadow-sm backdrop-blur"
                    >
                        <AnalyzerCurationPanel
                            run={run}
                            library={library}
                            workspaces={workspaces}
                            compact
                        />
                    </section>
                )}

                {!run.channel && !run.is_active && !run.error && (
                    <Alert>
                        <Database />
                        <AlertTitle>No profile data</AlertTitle>
                        <AlertDescription>
                            No reusable or newly collected observation was
                            pinned to this attempt.
                        </AlertDescription>
                    </Alert>
                )}
            </PageContainer>
        </>
    );
}

AnalyzerShow.layout = {
    breadcrumbs: [
        { title: 'Analyzer', href: '/analyzer' },
        { title: 'Analyzer profile', href: '#' },
    ],
};
