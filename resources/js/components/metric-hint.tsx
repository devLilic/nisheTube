import { CircleHelp } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export function MetricHint({
    label,
    children,
    className,
}: {
    label: string;
    children: string;
    className?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        className,
                    )}
                    aria-label={`Explain ${label}`}
                >
                    <CircleHelp className="size-3.5" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent className="max-w-72 leading-5" sideOffset={6}>
                <div>{children}</div>
            </TooltipContent>
        </Tooltip>
    );
}
