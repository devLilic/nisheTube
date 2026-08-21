import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    CircleX,
    LoaderCircle,
    ScanSearch,
    Sparkles,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ResearchRunStatus } from '@/types';

type RunState = ResearchRunStatus | 'partial';

const states: Record<
    RunState,
    { label: string; icon: typeof CircleDashed; className: string }
> = {
    queued: {
        label: 'Queued',
        icon: CircleDashed,
        className: 'border-border bg-muted text-muted-foreground',
    },
    searching: {
        label: 'Searching',
        icon: LoaderCircle,
        className: 'border-info/30 bg-info/10 text-info-foreground',
    },
    draft: {
        label: 'Preparing',
        icon: CircleDashed,
        className: 'border-border bg-muted text-muted-foreground',
    },
    enriching: {
        label: 'Enriching',
        icon: ScanSearch,
        className: 'border-info/30 bg-info/10 text-info-foreground',
    },
    scoring: {
        label: 'Scoring',
        icon: Sparkles,
        className: 'border-info/30 bg-info/10 text-info-foreground',
    },
    partial: {
        label: 'Partial data',
        icon: AlertTriangle,
        className: 'border-warning/40 bg-warning/15 text-warning-foreground',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle2,
        className: 'border-success/35 bg-success/12 text-success-foreground',
    },
    cancelled: {
        label: 'Cancelled',
        icon: CircleX,
        className: 'border-border bg-muted text-muted-foreground',
    },
    failed: {
        label: 'Failed',
        icon: AlertTriangle,
        className: 'border-destructive/30 bg-destructive/10 text-destructive',
    },
};

export function RunStatus({ state }: { state: RunState }) {
    const definition = states[state];
    const Icon = definition.icon;

    return (
        <Badge
            variant="outline"
            className={cn('gap-1.5', definition.className)}
        >
            <Icon
                className={cn(
                    ['searching', 'enriching', 'scoring'].includes(state) &&
                        'animate-pulse',
                )}
            />
            {definition.label}
        </Badge>
    );
}
