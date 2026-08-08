import { AlertTriangle, History } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function HistoryLoading() {
    return (
        <div
            className="space-y-6"
            aria-label="Loading research history"
            aria-busy="true"
        >
            <Card>
                <CardHeader className="space-y-3">
                    <Skeleton className="h-5 w-44" />
                    <Skeleton className="h-4 w-3/5" />
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                    <Skeleton className="h-10" />
                    <Skeleton className="h-10" />
                    <Skeleton className="h-10 w-28" />
                </CardContent>
            </Card>
            <Card>
                <CardContent className="space-y-4 pt-2">
                    {Array.from({ length: 5 }, (_, index) => (
                        <Skeleton key={index} className="h-16 w-full" />
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}

export function HistoryLoadError() {
    return (
        <Card className="border-destructive/30 bg-destructive/5">
            <CardContent className="flex flex-col items-center py-10 text-center">
                <AlertTriangle
                    className="size-7 text-destructive"
                    aria-hidden="true"
                />
                <h2 className="mt-3 font-semibold">
                    History could not be loaded
                </h2>
                <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                    Your saved runs remain unchanged. Refresh the page and try
                    again.
                </p>
            </CardContent>
        </Card>
    );
}

export function HistoryEmptyIcon() {
    return <History className="size-6" aria-hidden="true" />;
}
