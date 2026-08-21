import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type TableProps = ComponentProps<'table'> & {
    containerLabel?: string;
};

export function Table({
    className,
    containerLabel = 'Scrollable data table',
    ...props
}: TableProps) {
    return (
        <div
            className="relative w-full overflow-x-auto rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            tabIndex={0}
            role="region"
            aria-label={containerLabel}
        >
            <table
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

export function TableHeader({ className, ...props }: ComponentProps<'thead'>) {
    return <thead className={cn('[&_tr]:border-b', className)} {...props} />;
}

export function TableBody({ className, ...props }: ComponentProps<'tbody'>) {
    return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

export function TableRow({ className, ...props }: ComponentProps<'tr'>) {
    return <tr className={cn('border-b transition-colors hover:bg-muted/50', className)} {...props} />;
}

export function TableHead({ className, ...props }: ComponentProps<'th'>) {
    return <th className={cn('h-11 px-4 text-left align-middle text-xs font-semibold tracking-wide text-muted-foreground uppercase', className)} {...props} />;
}

export function TableCell({ className, ...props }: ComponentProps<'td'>) {
    return <td className={cn('p-4 align-middle', className)} {...props} />;
}
