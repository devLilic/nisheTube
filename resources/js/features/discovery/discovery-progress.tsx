import {
    CheckCircle2,
    CircleDashed,
    LoaderCircle,
    Sparkles,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DiscoveryRunStatus } from '@/types';

const steps = [
    { label: 'Queued', detail: 'Seed samples saved', threshold: 5 },
    { label: 'Analyzing', detail: 'Breakouts and topics', threshold: 25 },
    { label: 'Candidates', detail: 'Evidence persisted', threshold: 100 },
];

export function DiscoveryProgress({
    status,
    progress,
}: {
    status: DiscoveryRunStatus;
    progress: number;
}) {
    return (
        <div
            className="grid gap-3 md:grid-cols-3"
            aria-label="Discovery progress"
        >
            {steps.map((step, index) => {
                const complete = progress >= step.threshold;
                const current =
                    !complete &&
                    status !== 'failed' &&
                    (index === 0 || progress >= steps[index - 1].threshold);
                const Icon = complete
                    ? CheckCircle2
                    : current
                      ? LoaderCircle
                      : index === 2
                        ? Sparkles
                        : CircleDashed;

                return (
                    <div
                        key={step.label}
                        className={cn(
                            'rounded-xl border p-4',
                            complete && 'border-success/35 bg-success/8',
                            current && 'border-info/35 bg-info/8',
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <Icon
                                className={cn(
                                    'size-4',
                                    current &&
                                        'animate-spin text-info-foreground',
                                    complete && 'text-success-foreground',
                                )}
                                aria-hidden="true"
                            />
                            <span className="text-sm font-semibold">
                                {step.label}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {step.detail}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}
