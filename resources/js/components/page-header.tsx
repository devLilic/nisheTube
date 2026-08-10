import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageHeaderProps = {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
    compact?: boolean;
};

export function PageHeader({
    eyebrow,
    title,
    description,
    actions,
    compact = true,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col border-b border-border/70 lg:flex-row lg:justify-between',
                compact
                    ? 'gap-3 pb-4 lg:items-center'
                    : 'gap-4 pb-6 lg:items-end',
            )}
        >
            <div className="max-w-3xl">
                {eyebrow && !compact && (
                    <p
                        className={cn(
                            'text-xs font-semibold tracking-[0.18em] text-primary uppercase',
                            compact ? 'mb-1' : 'mb-2',
                        )}
                    >
                        {eyebrow}
                    </p>
                )}
                <h1
                    className={cn(
                        'font-semibold tracking-tight',
                        compact
                            ? 'text-xl sm:text-2xl'
                            : 'text-2xl sm:text-3xl',
                    )}
                >
                    {title}
                </h1>
                {description && (
                    <p
                        className={cn(
                            'max-w-2xl text-muted-foreground',
                            compact
                                ? 'mt-1 line-clamp-2 text-sm leading-5 lg:line-clamp-1'
                                : 'mt-2 text-sm leading-6 sm:text-base',
                        )}
                    >
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
