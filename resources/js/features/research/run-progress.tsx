import { Check, CircleDashed, LoaderCircle, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ResearchRunStatus } from '@/types';

const steps: Array<{
    status: Exclude<ResearchRunStatus, 'draft' | 'failed'>;
    label: string;
    description: string;
}> = [
    {
        status: 'queued',
        label: 'Queued',
        description: 'Waiting for the local worker',
    },
    {
        status: 'searching',
        label: 'Collecting',
        description: 'Paging through search results',
    },
    {
        status: 'enriching',
        label: 'Enriching',
        description: 'Preparing video and channel details',
    },
    {
        status: 'scoring',
        label: 'Scoring',
        description: 'Calculating opportunity signals',
    },
    {
        status: 'completed',
        label: 'Complete',
        description: 'Snapshot ready to inspect',
    },
];

export function RunProgress({
    status,
    startedAt,
    searchCompletedAt,
}: {
    status: ResearchRunStatus;
    startedAt: string | null;
    searchCompletedAt: string | null;
}) {
    const normalizedStatus = status === 'draft' ? 'queued' : status;
    const currentIndex = steps.findIndex(
        (step) => step.status === normalizedStatus,
    );
    const failed = status === 'failed';
    const failedIndex = searchCompletedAt ? 2 : startedAt ? 1 : 0;

    return (
        <ol
            className="grid gap-3 sm:grid-cols-5"
            aria-label="Research run progress"
        >
            {steps.map((step, index) => {
                const complete = !failed && index < currentIndex;
                const current = !failed && index === currentIndex;
                const Icon =
                    failed && index === failedIndex
                        ? X
                        : complete
                          ? Check
                          : current
                            ? LoaderCircle
                            : CircleDashed;

                return (
                    <li
                        key={step.status}
                        className={cn(
                            'rounded-xl border p-3',
                            complete && 'border-success/25 bg-success/6',
                            current && 'border-primary/35 bg-primary/6',
                            failed &&
                                index === failedIndex &&
                                'border-destructive/35 bg-destructive/6',
                        )}
                        aria-current={current ? 'step' : undefined}
                    >
                        <div className="flex items-center gap-2">
                            <Icon
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground',
                                    complete && 'text-success-foreground',
                                    current && 'animate-pulse text-primary',
                                    failed &&
                                        index === failedIndex &&
                                        'text-destructive',
                                )}
                                aria-hidden="true"
                            />
                            <span className="text-sm font-medium">
                                {step.label}
                            </span>
                        </div>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            {step.description}
                        </p>
                    </li>
                );
            })}
        </ol>
    );
}
