import { AlertCircle, Inbox, RefreshCw } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useId } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type StatePanelProps = {
    title: string;
    description: string;
    icon?: LucideIcon;
    actionLabel?: string;
    action?: ReactNode;
    tone?: 'neutral' | 'danger';
};

export function StatePanel({
    title,
    description,
    icon: Icon = Inbox,
    actionLabel,
    action,
    tone = 'neutral',
}: StatePanelProps) {
    const titleId = useId();

    return (
        <Card
            className={cn(
                'border-dashed py-8 text-center shadow-none',
                tone === 'danger' && 'border-destructive/35 bg-destructive/5',
            )}
            aria-labelledby={titleId}
            aria-live={tone === 'danger' ? 'assertive' : 'polite'}
            aria-atomic="true"
            role={tone === 'danger' ? 'alert' : 'status'}
        >
            <CardContent className="flex flex-col items-center px-6">
                <span
                    aria-hidden="true"
                    className={cn(
                        'mb-4 rounded-full bg-muted p-3 text-muted-foreground',
                        tone === 'danger' &&
                            'bg-destructive/10 text-destructive',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <h3 id={titleId} className="font-semibold">
                    {title}
                </h3>
                <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
                {action ? (
                    <div className="mt-5">{action}</div>
                ) : (
                    actionLabel && (
                        <Button variant="outline" size="sm" className="mt-5">
                            <RefreshCw />
                            {actionLabel}
                        </Button>
                    )
                )}
            </CardContent>
        </Card>
    );
}

export function EmptyState() {
    return (
        <StatePanel
            title="No research runs yet"
            description="Start a market-specific search to build your first observed-demand snapshot."
            actionLabel="Start a search"
        />
    );
}

export function ErrorState() {
    return (
        <StatePanel
            title="Collection could not finish"
            description="The saved run is safe. Check the connection and retry when the provider is available."
            icon={AlertCircle}
            actionLabel="Retry collection"
            tone="danger"
        />
    );
}

export function LoadingState() {
    return (
        <Card
            className="gap-4 py-5"
            aria-label="Loading research data"
            aria-busy="true"
            aria-live="polite"
            role="status"
        >
            <CardContent className="space-y-4 px-5">
                <div className="flex items-center gap-3">
                    <Skeleton className="size-10 rounded-lg" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-2/5" />
                        <Skeleton className="h-3 w-3/5" />
                    </div>
                </div>
                <Skeleton className="h-24 w-full" />
                <div className="grid grid-cols-3 gap-3">
                    <Skeleton className="h-8" />
                    <Skeleton className="h-8" />
                    <Skeleton className="h-8" />
                </div>
            </CardContent>
        </Card>
    );
}
