import { AlertTriangle } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function ExportJobsLoading() {
    return (
        <Card aria-label="Loading export jobs" aria-busy="true">
            <CardHeader className="space-y-3">
                <Skeleton className="h-5 w-36" />
                <Skeleton className="h-4 w-72" />
            </CardHeader>
            <CardContent className="space-y-3">
                {Array.from({ length: 3 }, (_, index) => (
                    <Skeleton key={index} className="h-16 w-full" />
                ))}
            </CardContent>
        </Card>
    );
}

export function ExportJobsError() {
    return (
        <Card className="border-destructive/30 bg-destructive/5">
            <CardContent className="flex flex-col items-center py-10 text-center">
                <AlertTriangle
                    className="size-7 text-destructive"
                    aria-hidden="true"
                />
                <h2 className="mt-3 font-semibold">
                    Export jobs could not be loaded
                </h2>
                <p className="mt-1 max-w-md text-sm text-muted-foreground">
                    Saved exports remain unchanged. Refresh the page to try
                    again.
                </p>
            </CardContent>
        </Card>
    );
}
