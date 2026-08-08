import type { LucideIcon } from 'lucide-react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type MetricCardProps = {
    label: string;
    value: string;
    detail: string;
    icon: LucideIcon;
    delta?: string;
    trend?: 'up' | 'down' | 'flat';
};

export function MetricCard({
    label,
    value,
    detail,
    icon: Icon,
    delta,
    trend = 'flat',
}: MetricCardProps) {
    const TrendIcon =
        trend === 'up'
            ? ArrowUpRight
            : trend === 'down'
              ? ArrowDownRight
              : Minus;

    return (
        <Card className="gap-4 overflow-hidden border-border/70 py-5 shadow-sm shadow-primary/5">
            <CardContent className="px-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            {label}
                        </p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                            {value}
                        </p>
                    </div>
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <Icon className="size-4" />
                    </span>
                </div>
                <div className="mt-4 flex items-center justify-between gap-3 text-xs">
                    <span className="truncate text-muted-foreground">
                        {detail}
                    </span>
                    {delta && (
                        <span
                            className={cn(
                                'flex shrink-0 items-center gap-1 font-semibold',
                                trend === 'up' && 'text-success-foreground',
                                trend === 'down' && 'text-destructive',
                                trend === 'flat' && 'text-muted-foreground',
                            )}
                        >
                            <TrendIcon className="size-3.5" />
                            {delta}
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
