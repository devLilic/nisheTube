import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Clock3,
    GitCompareArrows,
    ImageOff,
    ScanSearch,
} from 'lucide-react';
import { useState } from 'react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import type { PaginationMeta } from '@/components/pagination-controls';
import { PaginationControls } from '@/components/pagination-controls';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AnalyzerIntakeForm } from '@/features/analyzer/analyzer-intake-form';
import { AnalyzerStatus } from '@/features/analyzer/analyzer-status';
import type { AnalyzerRun } from '@/types';

type Props = {
    prefill: {
        target_kind: 'video' | 'channel';
        target_reference: string;
        origin_kind: string;
        origin_reference: string | null;
        return_to: string | null;
    };
    recent_video_runs: PaginationMeta & { data: AnalyzerRun[] };
    recent_channel_runs: PaginationMeta & { data: AnalyzerRun[] };
};

export default function AnalyzerIndex({
    prefill,
    recent_video_runs: recentVideoRuns,
    recent_channel_runs: recentChannelRuns,
}: Props) {
    return (
        <>
            <Head title="Analyzer" />
            <PageContainer>
                <PageHeader
                    eyebrow="Canonical video and channel research"
                    title="Analyzer"
                    description="Inspect one YouTube video or an author channel using immutable, source-aware observations."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/analyzer/compare">
                                <GitCompareArrows /> Compare channels
                            </Link>
                        </Button>
                    }
                />
                <div className="space-y-6">
                    <AnalyzerIntakeForm prefill={prefill} />
                    <section
                        aria-labelledby="recent-analyzer-results"
                        className="space-y-3"
                    >
                        <div className="flex items-center gap-2">
                            <Clock3 className="size-4 text-muted-foreground" />
                            <h2
                                id="recent-analyzer-results"
                                className="text-sm font-semibold"
                            >
                                Recent Analyzer results
                            </h2>
                        </div>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <RecentAnalysisGroup
                                title="Recent videos"
                                emptyTitle="No video analyses yet"
                                emptyDescription="Analyze a supported video URL or ID to create your first video profile."
                                runs={recentVideoRuns}
                                onPageChange={(page) =>
                                    changeHistoryPage(
                                        prefill,
                                        page,
                                        recentChannelRuns.current_page,
                                    )
                                }
                            />
                            <RecentAnalysisGroup
                                title="Recent channels"
                                emptyTitle="No channel analyses yet"
                                emptyDescription="Analyze a supported channel URL or ID to create your first channel profile."
                                runs={recentChannelRuns}
                                onPageChange={(page) =>
                                    changeHistoryPage(
                                        prefill,
                                        recentVideoRuns.current_page,
                                        page,
                                    )
                                }
                            />
                        </div>
                    </section>
                </div>
            </PageContainer>
        </>
    );
}

function RecentAnalysisGroup({
    title,
    emptyTitle,
    emptyDescription,
    runs,
    onPageChange,
}: {
    title: string;
    emptyTitle: string;
    emptyDescription: string;
    runs: PaginationMeta & { data: AnalyzerRun[] };
    onPageChange: (page: number) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {runs.data.length === 0 ? (
                    <div className="flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed px-5 text-center">
                        <ScanSearch className="size-7 text-muted-foreground" />
                        <p className="mt-3 text-sm font-medium">{emptyTitle}</p>
                        <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                            {emptyDescription}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {runs.data.map((run) => (
                            <Link
                                key={run.public_id}
                                href={`/analyzer/runs/${run.public_id}`}
                                className="group flex items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
                            >
                                <RecentThumbnail run={run} />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="line-clamp-2 text-sm font-medium group-hover:text-primary"
                                        title={run.display_label}
                                    >
                                        {run.display_label}
                                    </p>
                                    <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                        {run.target_kind === 'video' &&
                                        run.display_identity.channel_title
                                            ? `${run.display_identity.channel_title} · Video analysis`
                                            : `${run.target_kind === 'video' ? 'Video' : 'Channel'} analysis`}
                                    </p>
                                    {!run.display_identity.is_resolved && (
                                        <p className="mt-1 truncate text-[0.7rem] text-muted-foreground/80">
                                            YouTube ID:{' '}
                                            {run.display_identity.provider_id}
                                        </p>
                                    )}
                                    <div className="mt-2">
                                        <AnalyzerStatus status={run.status} />
                                    </div>
                                </div>
                                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        ))}
                    </div>
                )}
                <div className="mt-4">
                    <PaginationControls
                        pagination={runs}
                        onPageChange={onPageChange}
                        ariaLabel={`${title} pagination`}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function RecentThumbnail({ run }: { run: AnalyzerRun }) {
    const [failed, setFailed] = useState(false);
    const thumbnail = run.display_identity.thumbnail_url;

    if (!thumbnail || failed) {
        return (
            <span
                className="flex size-16 shrink-0 items-center justify-center rounded-lg border bg-muted text-muted-foreground"
                aria-label={`${run.target_kind === 'video' ? 'Video' : 'Channel'} thumbnail unavailable`}
            >
                <ImageOff className="size-5" aria-hidden="true" />
            </span>
        );
    }

    return (
        <img
            src={thumbnail}
            alt=""
            className={`size-16 shrink-0 border bg-muted object-cover ${run.target_kind === 'channel' ? 'rounded-full' : 'rounded-lg'}`}
            loading="lazy"
            onError={() => setFailed(true)}
        />
    );
}

function changeHistoryPage(
    prefill: Props['prefill'],
    videoPage: number,
    channelPage: number,
) {
    router.get(
        '/analyzer',
        {
            video_page: videoPage,
            channel_page: channelPage,
            ...(prefill.target_reference
                ? {
                      [prefill.target_kind]: prefill.target_reference,
                      origin: prefill.origin_kind,
                      origin_reference: prefill.origin_reference,
                      return_to: prefill.return_to,
                  }
                : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

AnalyzerIndex.layout = {
    breadcrumbs: [{ title: 'Analyzer', href: '/analyzer' }],
};
