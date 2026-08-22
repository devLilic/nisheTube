import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import {
    AlertCircle,
    CheckCircle2,
    Clock3,
    Info,
    Inbox,
    RefreshCw,
} from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type SurfaceProps = ComponentProps<'section'> & { children: ReactNode };

export function AppCanvas({ className, children, ...props }: SurfaceProps) {
    return (
        <div
            className={cn(
                'min-h-full bg-[radial-gradient(circle_at_12%_0%,var(--lg-canvas-cool),transparent_32rem),radial-gradient(circle_at_88%_8%,var(--lg-canvas-violet),transparent_34rem)] px-4 py-6 sm:px-5 lg:px-6',
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}

export function GlassCommandBar({
    className,
    children,
    ...props
}: SurfaceProps) {
    return (
        <section
            className={cn(
                'rounded-3xl px-4 py-3 lg-material-command',
                className,
            )}
            {...props}
        >
            {children}
        </section>
    );
}

export function GlassNavigationRail({
    className,
    children,
    ...props
}: SurfaceProps) {
    return (
        <nav
            className={cn('rounded-3xl p-3 lg-material-command', className)}
            aria-label="Research navigation"
            {...props}
        >
            {children}
        </nav>
    );
}

export function GlassPanel({ className, children, ...props }: SurfaceProps) {
    return (
        <section
            className={cn('rounded-[20px] p-5 lg-material-panel', className)}
            {...props}
        >
            {children}
        </section>
    );
}

const capsuleVariants = cva(
    'inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 py-1 text-xs leading-4 font-semibold',
    {
        variants: {
            tone: {
                neutral:
                    'border-[var(--lg-border-soft)] bg-white/70 text-[var(--lg-ink)]',
                selected: 'border-primary/25 bg-primary/12 text-primary',
                info: 'border-info/25 bg-info/10 text-info-foreground',
                warning:
                    'border-warning/30 bg-warning/10 text-warning-foreground',
                danger: 'border-destructive/30 bg-destructive/10 text-destructive',
                success:
                    'border-success/30 bg-success/10 text-success-foreground',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

export function GlassCapsule({
    className,
    tone,
    ...props
}: ComponentProps<'span'> & VariantProps<typeof capsuleVariants>) {
    return (
        <span className={cn(capsuleVariants({ tone }), className)} {...props} />
    );
}

const contentVariants = cva('rounded-2xl lg-material-solid', {
    variants: {
        tone: {
            regular: '',
            dense: 'rounded-[14px]',
            quiet: 'bg-white/94',
            critical: 'border-destructive/50 bg-destructive/5',
        },
    },
    defaultVariants: { tone: 'regular' },
});

export function ContentPanel({
    className,
    tone,
    ...props
}: SurfaceProps & VariantProps<typeof contentVariants>) {
    return (
        <section
            className={cn(contentVariants({ tone }), className)}
            {...props}
        />
    );
}

type MetricTileProps = {
    label: string;
    value: string;
    basis: string;
    confidence?: string;
    unavailableReason?: string;
    delta?: string;
    className?: string;
};
export function MetricTile({
    label,
    value,
    basis,
    confidence,
    unavailableReason,
    delta,
    className,
}: MetricTileProps) {
    return (
        <ContentPanel className={cn('min-w-0 p-4', className)}>
            <p className="text-sm font-medium text-[var(--lg-ink-muted)]">
                {label}
            </p>
            <p className="mt-2 text-[28px] leading-8 font-bold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="mt-2 text-xs leading-5 text-[var(--lg-ink-muted)]">
                {unavailableReason ?? basis}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {confidence && (
                    <GlassCapsule tone="info">{confidence}</GlassCapsule>
                )}
                {delta && (
                    <span className="text-xs font-semibold text-success-foreground">
                        {delta}
                    </span>
                )}
            </div>
        </ContentPanel>
    );
}

type EvidenceRowProps = {
    title: string;
    source: string;
    timestamp: string;
    metrics: ReadonlyArray<{ label: string; value: string }>;
    action?: ReactNode;
    className?: string;
};
export function EvidenceRow({
    title,
    source,
    timestamp,
    metrics,
    action,
    className,
}: EvidenceRowProps) {
    return (
        <article
            className={cn(
                'grid gap-3 border-b border-[var(--lg-border-soft)] p-4 last:border-b-0 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center',
                className,
            )}
        >
            <div className="min-w-0">
                <h3 className="min-h-10 font-semibold break-words">{title}</h3>
                <p className="mt-1 text-xs text-[var(--lg-ink-muted)]">
                    {source} · Observed {timestamp}
                </p>
            </div>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-right text-xs tabular-nums">
                {metrics.slice(0, 4).map((metric) => (
                    <div key={metric.label}>
                        <dt className="text-[var(--lg-ink-muted)]">
                            {metric.label}
                        </dt>
                        <dd className="font-semibold">{metric.value}</dd>
                    </div>
                ))}
            </dl>
            {action && <div className="flex justify-end">{action}</div>}
        </article>
    );
}

type DataTableFrameProps = {
    caption: string;
    children: ReactNode;
    state?: 'loading' | 'empty' | 'partial' | 'success' | 'error';
    className?: string;
};
export function DataTableFrame({
    caption,
    children,
    state = 'success',
    className,
}: DataTableFrameProps) {
    return (
        <ContentPanel tone="dense" className={cn('overflow-hidden', className)}>
            <div className="border-b border-[var(--lg-border-soft)] px-4 py-3">
                <p className="text-sm font-semibold">{caption}</p>
                {state === 'partial' && (
                    <p className="mt-1 text-xs text-warning-foreground">
                        <Info
                            className="mr-1 inline size-3.5"
                            aria-hidden="true"
                        />
                        Partial coverage; unavailable values remain identified.
                    </p>
                )}
            </div>
            <div
                className="overflow-x-auto"
                tabIndex={0}
                role="region"
                aria-label={caption}
            >
                {children}
            </div>
        </ContentPanel>
    );
}

type InspectorPanelProps = {
    title: string;
    description: string;
    children: ReactNode;
    className?: string;
};
export function InspectorPanel({
    title,
    description,
    children,
    className,
}: InspectorPanelProps) {
    return (
        <GlassPanel className={cn(className)} aria-label={title}>
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="mt-1 text-sm leading-6 text-[var(--lg-ink-muted)]">
                {description}
            </p>
            <div className="mt-4 border-t border-[var(--lg-border-soft)] pt-4 text-sm leading-6">
                {children}
            </div>
        </GlassPanel>
    );
}

type ChartFrameProps = {
    title: string;
    source: string;
    sampleSize: string;
    children: ReactNode;
    exactValues: ReactNode;
    className?: string;
};
export function ChartFrame({
    title,
    source,
    sampleSize,
    children,
    exactValues,
    className,
}: ChartFrameProps) {
    return (
        <ContentPanel className={cn('p-4', className)}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="text-xs text-[var(--lg-ink-muted)]">
                        {source} · {sampleSize}
                    </p>
                </div>
                <GlassCapsule tone="neutral">Fixture chart</GlassCapsule>
            </div>
            <div className="mt-4 min-h-36" aria-label={`${title} chart`}>
                {children}
            </div>
            <details className="mt-4 border-t border-[var(--lg-border-soft)] pt-3">
                <summary className="cursor-pointer text-sm font-semibold">
                    View exact values
                </summary>
                {exactValues}
            </details>
        </ContentPanel>
    );
}

type AsyncSectionProps = {
    state: 'idle' | 'queued' | 'processing' | 'completed' | 'failed';
    title: string;
    description: string;
    onRetry?: () => void;
    className?: string;
};
const asyncIcon = {
    idle: Inbox,
    queued: Clock3,
    processing: Clock3,
    completed: CheckCircle2,
    failed: AlertCircle,
};
export function AsyncSection({
    state,
    title,
    description,
    onRetry,
    className,
}: AsyncSectionProps) {
    const Icon = asyncIcon[state];

    return (
        <ContentPanel
            className={cn('min-h-36 p-4', className)}
            aria-busy={state === 'queued' || state === 'processing'}
            aria-live="polite"
        >
            <div className="flex items-start gap-3">
                <Icon
                    className={cn(
                        'mt-0.5 size-5 shrink-0',
                        state === 'failed'
                            ? 'text-destructive'
                            : state === 'completed'
                              ? 'text-success-foreground'
                              : 'text-info-foreground',
                    )}
                    aria-hidden="true"
                />
                <div className="min-w-0 flex-1">
                    <h2 className="font-semibold">
                        {title}{' '}
                        <span className="font-normal text-[var(--lg-ink-muted)]">
                            — {state}
                        </span>
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-[var(--lg-ink-muted)]">
                        {description}
                    </p>
                    {state === 'processing' && (
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <span className="block h-full w-1/2 bg-info" />
                        </div>
                    )}
                    {state === 'failed' && (
                        <Button
                            className="mt-3 min-h-11"
                            type="button"
                            variant="outline"
                            onClick={onRetry}
                        >
                            <RefreshCw />
                            Retry safely
                        </Button>
                    )}
                </div>
            </div>
        </ContentPanel>
    );
}

export function GlassPopover({
    trigger,
    title,
    children,
}: {
    trigger: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <details className="relative inline-block">
            <summary className="cursor-pointer list-none">{trigger}</summary>
            <div className="absolute right-0 z-10 mt-2 w-72 rounded-[20px] p-4 lg-material-panel">
                <p className="font-semibold">{title}</p>
                <div className="mt-2 text-sm text-[var(--lg-ink-muted)]">
                    {children}
                </div>
            </div>
        </details>
    );
}

export function GlassSheet({
    trigger,
    title,
    description,
    children,
    side = 'right',
    open,
    onOpenChange,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    children: ReactNode;
    side?: 'left' | 'right' | 'bottom';
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetTrigger asChild>{trigger}</SheetTrigger>
            <SheetContent
                side={side}
                className="w-[min(92vw,28rem)] border-0 p-0 lg-material-command"
                closeLabel="Close panel"
            >
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                <div className="px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    {children}
                </div>
            </SheetContent>
        </Sheet>
    );
}

export function LoadingFixture({ className }: { className?: string }) {
    return (
        <ContentPanel
            className={cn('space-y-3 p-4', className)}
            aria-busy="true"
            aria-label="Loading fixture"
        >
            <Skeleton className="h-5 w-2/5" />
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-8 w-4/5" />
        </ContentPanel>
    );
}
