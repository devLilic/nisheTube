import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { QuotaBucketSummary } from '@/types';

export function quotaBucketLabel(bucket: string) {
    return bucket
        .split(/[_-]/)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function QuotaMeter({
    summary,
    compact = false,
}: {
    summary: QuotaBucketSummary;
    compact?: boolean;
}) {
    const percentage = Math.min(
        100,
        Math.round((summary.used / summary.allowance) * 100),
    );
    const Icon = summary.exhausted ? AlertTriangle : CheckCircle2;

    return (
        <div className={cn('space-y-2', compact && 'space-y-1.5')}>
            <div className="flex items-center justify-between gap-3 text-sm">
                <span className="flex items-center gap-1.5 font-medium">
                    <Icon
                        className={cn(
                            'size-4',
                            summary.exhausted
                                ? 'text-destructive'
                                : 'text-success-foreground',
                        )}
                        aria-hidden="true"
                    />
                    {quotaBucketLabel(summary.bucket)}
                </span>
                <span className="text-muted-foreground tabular-nums">
                    {summary.remaining.toLocaleString()} /{' '}
                    {summary.allowance.toLocaleString()} left
                </span>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-label={`${quotaBucketLabel(summary.bucket)} quota used`}
                aria-valuemin={0}
                aria-valuemax={summary.allowance}
                aria-valuenow={summary.used}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width]',
                        summary.exhausted ? 'bg-destructive' : 'bg-primary',
                    )}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {!compact && (
                <p className="text-xs text-muted-foreground">
                    {summary.used.toLocaleString()} estimated used today
                    {summary.last_endpoint
                        ? ` · Last: ${summary.last_endpoint} (${summary.last_cost ?? 0})`
                        : ' · No calls recorded today'}
                </p>
            )}
        </div>
    );
}
