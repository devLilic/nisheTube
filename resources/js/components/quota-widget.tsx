import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Clock3,
    CloudOff,
    LoaderCircle,
    RefreshCw,
    Youtube,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { QuotaMeter, quotaBucketLabel } from '@/components/quota-meter';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { edit as editYouTube } from '@/routes/youtube';
import type { QuotaSummary } from '@/types';

const POLL_INTERVAL = 60_000;
const STALE_AFTER = 120_000;

function formatTimestamp(value: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

export function QuotaWidget() {
    const page = usePage();
    const summary: QuotaSummary | null | undefined = page.props.youtubeQuota;
    const timezone = page.props.auth.user.timezone;
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const refresh = () => {
            setNow(Date.now());

            if (document.visibilityState === 'visible') {
                router.reload({
                    only: ['youtubeQuota'],
                });
            }
        };

        const interval = window.setInterval(refresh, POLL_INTERVAL);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', refresh);
        };
    }, []);

    const isLoading = summary === undefined;
    const isUnavailable = summary === null;
    const isStale =
        summary !== null &&
        summary !== undefined &&
        now - new Date(summary.generated_at).getTime() > STALE_AFTER;
    const isExhausted = summary?.buckets.some((bucket) => bucket.exhausted);
    const Icon = isLoading
        ? LoaderCircle
        : isUnavailable
          ? CloudOff
          : isExhausted
            ? AlertTriangle
            : isStale
              ? Clock3
              : Youtube;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={cn(
                        'max-w-[22rem] min-w-0 gap-2 overflow-hidden bg-background/70 px-2.5',
                        isExhausted && 'border-destructive/50',
                    )}
                    aria-label="Open YouTube API quota estimate"
                >
                    <Icon
                        className={cn(
                            'size-4',
                            isLoading && 'animate-spin',
                            isExhausted && 'text-destructive',
                        )}
                        aria-hidden="true"
                    />
                    <span className="hidden shrink-0 font-medium xl:inline">
                        YouTube API Today
                    </span>
                    {summary?.buckets.slice(0, 2).map((bucket) => (
                        <span
                            key={bucket.bucket}
                            className="hidden min-w-0 truncate border-l pl-2 text-xs text-muted-foreground tabular-nums xl:inline"
                        >
                            {quotaBucketLabel(bucket.bucket)}:{' '}
                            {bucket.remaining.toLocaleString()}{' '}
                            {bucket.measure === 'requests' ? 'req.' : 'units'}
                        </span>
                    ))}
                    <span className="shrink-0 text-xs xl:hidden">
                        {isUnavailable
                            ? 'API N/A'
                            : isLoading
                              ? 'API…'
                              : `API ${summary?.buckets[0]?.remaining.toLocaleString() ?? '—'}`}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(24rem,calc(100vw-2rem))] p-0"
            >
                <DropdownMenuLabel className="space-y-1.5 p-4">
                    <span className="flex items-center justify-between gap-3">
                        <span>YouTube API Today</span>
                        {isStale && (
                            <span className="flex items-center gap-1 text-xs font-normal text-warning-foreground">
                                <RefreshCw className="size-3" /> Stale
                            </span>
                        )}
                    </span>
                    <span className="block text-xs font-normal text-muted-foreground">
                        NisheTube estimate · Project-wide usage
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="m-0" />
                <div className="space-y-4 p-4">
                    {isLoading && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LoaderCircle className="size-4 animate-spin" />
                            Loading quota estimate…
                        </div>
                    )}
                    {isUnavailable && (
                        <div className="flex items-start gap-2 text-sm text-muted-foreground">
                            <CloudOff className="mt-0.5 size-4 shrink-0" />
                            Quota data is unavailable. Open settings to review
                            the local integration.
                        </div>
                    )}
                    {summary?.buckets.map((bucket) => (
                        <div key={bucket.bucket} className="space-y-1.5">
                            <QuotaMeter summary={bucket} compact />
                            <p className="text-xs leading-5 text-muted-foreground">
                                {bucket.last_endpoint &&
                                bucket.last_occurred_at ? (
                                    <>
                                        Last NisheTube-recorded call:{' '}
                                        {bucket.last_endpoint} ·{' '}
                                        {bucket.last_cost?.toLocaleString() ??
                                            0}{' '}
                                        estimated {bucket.measure} ·{' '}
                                        {formatTimestamp(
                                            bucket.last_occurred_at,
                                            timezone,
                                        )}
                                    </>
                                ) : (
                                    <>No NisheTube-recorded calls today.</>
                                )}
                            </p>
                        </div>
                    ))}
                    {summary && (
                        <div className="space-y-1 border-t pt-3 text-xs leading-5 text-muted-foreground">
                            <p>
                                Search tracks NisheTube-recorded requests;
                                General API tracks Google-estimated units.
                                Endpoint costs come from provider configuration.
                            </p>
                            <p>
                                Resets at{' '}
                                {formatTimestamp(summary.reset_at, timezone)}.
                            </p>
                            <p>
                                Google Cloud Console is authoritative; usage by
                                other apps may not appear here.
                            </p>
                        </div>
                    )}
                </div>
                <DropdownMenuSeparator className="m-0" />
                <DropdownMenuItem asChild className="m-1.5">
                    <Link href={editYouTube()}>Manage YouTube settings</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
