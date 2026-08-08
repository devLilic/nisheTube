import { cn } from '@/lib/utils';

export function ScoreGauge({
    score,
    label,
    className,
}: {
    score: number;
    label: string;
    className?: string;
}) {
    const boundedScore = Math.min(100, Math.max(0, score));
    const circumference = 2 * Math.PI * 52;
    const dashOffset = circumference * (1 - boundedScore / 100);

    return (
        <div
            className={cn(
                'relative grid size-44 shrink-0 place-items-center',
                className,
            )}
            role="meter"
            aria-label={`Opportunity score: ${boundedScore.toFixed(1)} out of 100, ${label}`}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={boundedScore}
            aria-valuetext={`${boundedScore.toFixed(1)} out of 100, ${label}`}
        >
            <svg
                viewBox="0 0 120 120"
                className="absolute inset-0 size-full -rotate-90"
                aria-hidden="true"
            >
                <circle
                    cx="60"
                    cy="60"
                    r="52"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="9"
                    className="text-muted"
                />
                <circle
                    cx="60"
                    cy="60"
                    r="52"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="9"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={dashOffset}
                    className="text-primary transition-[stroke-dashoffset] duration-700"
                />
            </svg>
            <div className="relative text-center">
                <span className="block text-4xl font-semibold tracking-tight tabular-nums">
                    {boundedScore.toFixed(1)}
                </span>
                <span className="mt-1 block text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    out of 100
                </span>
            </div>
        </div>
    );
}
