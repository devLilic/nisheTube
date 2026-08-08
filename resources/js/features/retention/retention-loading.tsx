import { AlertTriangle } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function RetentionLoading() {
    return (
        <div
            className="space-y-6"
            aria-label="Loading retention data"
            aria-busy="true"
        >
            <Card>
                <CardHeader className="space-y-3">
                    <Skeleton className="h-6 w-56" />
                    <Skeleton className="h-4 w-full max-w-xl" />
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {Array.from({ length: 4 }, (_, index) => (
                            <Skeleton key={index} className="h-20" />
                        ))}
                    </div>
                    <Skeleton className="h-28 w-full" />
                </CardContent>
            </Card>
            <Skeleton className="h-72 w-full" />
            <Skeleton className="h-48 w-full" />
        </div>
    );
}

export function RetentionError() {
    return (
        <Card className="border-destructive/30 bg-destructive/5">
            <CardContent className="flex flex-col items-center py-12 text-center">
                <AlertTriangle
                    className="size-8 text-destructive"
                    aria-hidden="true"
                />
                <h2 className="mt-3 font-semibold">
                    Retention data could not be loaded
                </h2>
                <p className="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                    No cleanup was started and saved research is unchanged.
                    Refresh the page to try again.
                </p>
            </CardContent>
        </Card>
    );
}
