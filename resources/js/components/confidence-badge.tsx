import { ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export function ConfidenceBadge({
    score,
    label,
}: {
    score: number;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 px-2.5 py-1 tabular-nums',
                score >= 80 &&
                    'border-success/40 bg-success/10 text-success-foreground',
                score >= 60 &&
                    score < 80 &&
                    'border-info/40 bg-info/10 text-info-foreground',
                score >= 40 &&
                    score < 60 &&
                    'border-warning/45 bg-warning/10 text-warning-foreground',
                score < 40 &&
                    'border-destructive/35 bg-destructive/8 text-destructive',
            )}
        >
            <ShieldCheck aria-hidden="true" />
            {label} · {score.toFixed(1)}
        </Badge>
    );
}
