import { Deferred, Head, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    RetentionError,
    RetentionLoading,
} from '@/features/retention/retention-loading';
import { RetentionWorkspace } from '@/features/retention/retention-workspace';
import type { Auth, RetentionWorkspaceData } from '@/types';

type Props = {
    auth: Auth;
    retention?: RetentionWorkspaceData;
};

export default function Retention({ retention }: Props) {
    const { auth } = usePage<Props>().props;
    const { start, stop } = usePoll(
        2000,
        { only: ['retention'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (retention?.has_active) {
            start();
        } else {
            stop();
        }

        return () => stop();
    }, [retention?.has_active, start, stop]);

    return (
        <>
            <Head title="Data retention" />
            <h1 className="sr-only">Data retention settings</h1>
            <Deferred
                data="retention"
                fallback={<RetentionLoading />}
                rescue={<RetentionError />}
            >
                {retention ? (
                    <RetentionWorkspace
                        data={retention}
                        timezone={auth.user.timezone}
                    />
                ) : (
                    <RetentionLoading />
                )}
            </Deferred>
        </>
    );
}

Retention.layout = {
    breadcrumbs: [{ title: 'Data retention', href: '/settings/retention' }],
};
