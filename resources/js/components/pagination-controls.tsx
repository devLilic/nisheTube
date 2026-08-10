import { Button } from '@/components/ui/button';

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

export function PaginationControls({
    pagination,
    onPageChange,
    ariaLabel = 'Results pagination',
}: {
    pagination: PaginationMeta;
    onPageChange: (page: number) => void;
    ariaLabel?: string;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            aria-label={ariaLabel}
        >
            <p className="text-sm text-muted-foreground">
                Showing {pagination.from ?? 0}–{pagination.to ?? 0} of{' '}
                {pagination.total}
            </p>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={pagination.current_page <= 1}
                    onClick={() => onPageChange(pagination.current_page - 1)}
                >
                    Previous
                </Button>
                <span className="min-w-24 text-center text-sm tabular-nums">
                    Page {pagination.current_page} of {pagination.last_page}
                </span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={pagination.current_page >= pagination.last_page}
                    onClick={() => onPageChange(pagination.current_page + 1)}
                >
                    Next
                </Button>
            </div>
        </nav>
    );
}
