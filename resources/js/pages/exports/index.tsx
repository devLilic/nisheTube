import { Deferred, Head, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import {
    ExportJobsError,
    ExportJobsLoading,
} from '@/features/exports/export-loading';
import { ExportWorkspace } from '@/features/exports/export-workspace';
import type { Auth, ExportBuilderData, ExportJobsData } from '@/types';

type Props = { auth: Auth; builder: ExportBuilderData; jobs?: ExportJobsData };

export default function ExportsIndex({ builder, jobs }: Props) {
    const { auth } = usePage<Props>().props;
    const { start, stop } = usePoll(
        2000,
        { only: ['jobs'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (jobs?.has_active) {
            start();
        } else {
            stop();
        }

        return () => stop();
    }, [jobs?.has_active, start, stop]);

    return (
        <>
            <Head title="Exports" />
            <PageContainer>
                <PageHeader
                    eyebrow="Portable research evidence"
                    title="Exports"
                    description="Create private CSV or Excel files from immutable stored research. Choose the runs and columns, then download the generated file before it expires."
                />
                <Deferred
                    data="jobs"
                    fallback={<ExportJobsLoading />}
                    rescue={<ExportJobsError />}
                >
                    {jobs ? (
                        <ExportWorkspace
                            builder={builder}
                            jobs={jobs}
                            timezone={auth.user.timezone}
                        />
                    ) : (
                        <ExportJobsLoading />
                    )}
                </Deferred>
            </PageContainer>
        </>
    );
}

ExportsIndex.layout = { breadcrumbs: [{ title: 'Exports', href: '/exports' }] };
