import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    LoaderCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { AnalyzerRunStatus } from '@/types';

const labels: Record<AnalyzerRunStatus, string> = {
    queued: 'Queued',
    fetching_video: 'Fetching video',
    fetching_channel: 'Fetching channel',
    loading_recent_videos: 'Loading recent videos',
    calculating_metrics: 'Calculating metrics',
    saving_analysis: 'Saving analysis',
    completed: 'Completed',
    failed: 'Failed',
};

export function AnalyzerStatus({ status }: { status: AnalyzerRunStatus }) {
    const active = !['queued', 'completed', 'failed'].includes(status);
    const Icon =
        status === 'completed'
            ? CheckCircle2
            : status === 'failed'
              ? AlertTriangle
              : active
                ? LoaderCircle
                : CircleDashed;

    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5',
                status === 'completed' &&
                    'border-success/35 bg-success/12 text-success-foreground',
                status === 'failed' &&
                    'border-destructive/30 bg-destructive/10 text-destructive',
                active && 'border-info/30 bg-info/10 text-info-foreground',
            )}
        >
            <Icon className={cn('size-3.5', active && 'animate-spin')} />
            {labels[status]}
        </Badge>
    );
}
