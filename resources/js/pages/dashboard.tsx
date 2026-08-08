import { Deferred, Head, Link, usePage, usePoll } from '@inertiajs/react';
import { Search, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    DashboardLoadError,
    DashboardLoading,
} from '@/features/dashboard/dashboard-loading';
import { DashboardOverview } from '@/features/dashboard/dashboard-overview';
import { dashboard } from '@/routes';
import { create } from '@/routes/research';
import type { Auth, DashboardData } from '@/types';

type PageProps = {
    auth: Auth;
    dashboard?: DashboardData;
};

export default function Dashboard({ dashboard: dashboardData }: PageProps) {
    const { auth } = usePage<PageProps>().props;
    const { start, stop } = usePoll(
        5000,
        { only: ['dashboard'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if ((dashboardData?.counts.active_runs ?? 0) > 0) {
            start();
        } else {
            stop();
        }

        return () => stop();
    }, [dashboardData?.counts.active_runs, start, stop]);

    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader
                    eyebrow="Research command center"
                    title={`Welcome back, ${auth.user.name.split(' ')[0]}`}
                    description="Track active collection, compare recent opportunity signals, and decide what to research next. Scores summarize observed returned-video evidence, not YouTube search volume."
                    actions={
                        <>
                            <Button asChild>
                                <Link href={create()}>
                                    <Search aria-hidden="true" />
                                    New search
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                disabled
                                title="Discovery becomes available in the Discovery milestone"
                            >
                                <Sparkles aria-hidden="true" />
                                Start discovery
                            </Button>
                        </>
                    }
                />

                <Deferred
                    data="dashboard"
                    fallback={<DashboardLoading />}
                    rescue={<DashboardLoadError />}
                >
                    {dashboardData ? (
                        <DashboardOverview
                            dashboard={dashboardData}
                            timezone={auth.user.timezone}
                        />
                    ) : (
                        <DashboardLoading />
                    )}
                </Deferred>
            </PageContainer>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
