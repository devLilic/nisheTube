import { Deferred, Head, usePage } from '@inertiajs/react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import {
    HistoryLoadError,
    HistoryLoading,
} from '@/features/history/history-loading';
import { HistoryOverview } from '@/features/history/history-overview';
import type { Auth, HistoryIndexData } from '@/types';

type Props = {
    auth: Auth;
    history?: HistoryIndexData;
};

export default function HistoryIndex({ history }: Props) {
    const { auth } = usePage<Props>().props;

    return (
        <>
            <Head title="History" />
            <PageContainer>
                <PageHeader
                    eyebrow="Immutable research record"
                    title="History"
                    description="Review timestamped search and validation runs, then compare useful snapshots without rewriting their stored metrics or score versions."
                />
                <Deferred
                    data="history"
                    fallback={<HistoryLoading />}
                    rescue={<HistoryLoadError />}
                >
                    {history ? (
                        <HistoryOverview
                            history={history}
                            timezone={auth.user.timezone}
                        />
                    ) : (
                        <HistoryLoading />
                    )}
                </Deferred>
            </PageContainer>
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'History', href: '/history' }],
};
