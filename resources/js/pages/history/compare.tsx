import { Deferred, Head, usePage } from '@inertiajs/react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { ComparisonView } from '@/features/history/comparison-view';
import {
    HistoryLoadError,
    HistoryLoading,
} from '@/features/history/history-loading';
import type { Auth, HistoryComparison, HistoryPair } from '@/types';

type Props = {
    auth: Auth;
    pair: HistoryPair;
    comparison?: HistoryComparison;
};

export default function HistoryCompare({ pair, comparison }: Props) {
    const { auth } = usePage<Props>().props;

    return (
        <>
            <Head title={`Compare ${pair.before.query_text}`} />
            <PageContainer>
                <PageHeader
                    eyebrow="History comparison"
                    title="Snapshot changes"
                    description="Inspect score, metric, video, and channel composition changes from immutable stored evidence."
                />
                <Deferred
                    data="comparison"
                    fallback={<HistoryLoading />}
                    rescue={<HistoryLoadError />}
                >
                    {comparison ? (
                        <ComparisonView
                            pair={pair}
                            comparison={comparison}
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

HistoryCompare.layout = {
    breadcrumbs: [
        { title: 'History', href: '/history' },
        { title: 'Compare', href: '#' },
    ],
};
