import { router } from '@inertiajs/react';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function DashboardLoading() {
    return (
        <div
            className="space-y-6"
            aria-label="Loading dashboard data"
            aria-busy="true"
        >
            <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                {Array.from({ length: 4 }, (_, index) => (
                    <Card key={index} className="gap-4 py-5">
                        <CardContent className="space-y-3 px-5">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex-1 space-y-3">
                                    <Skeleton className="h-4 w-2/3" />
                                    <Skeleton className="h-8 w-1/3" />
                                </div>
                                <Skeleton className="size-9 rounded-lg" />
                            </div>
                            <Skeleton className="h-3 w-4/5" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Skeleton className="h-20 w-full rounded-lg" />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(20rem,0.85fr)]">
                <Card>
                    <CardContent className="space-y-4 px-6">
                        <Skeleton className="h-6 w-48" />
                        {Array.from({ length: 4 }, (_, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-[1fr_8rem_8rem] gap-4 border-t pt-4"
                            >
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-4/5" />
                                    <Skeleton className="h-3 w-2/5" />
                                </div>
                                <Skeleton className="h-6 w-24" />
                                <Skeleton className="h-6 w-24" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="space-y-4 px-6">
                        <Skeleton className="h-6 w-44" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export function DashboardLoadError() {
    return (
        <Card className="border-destructive/35 bg-destructive/5 py-10 text-center shadow-none">
            <CardContent className="flex flex-col items-center px-6">
                <span className="rounded-full bg-destructive/10 p-3 text-destructive">
                    <AlertCircle className="size-5" aria-hidden="true" />
                </span>
                <h2 className="mt-4 font-semibold">
                    Dashboard data could not load
                </h2>
                <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                    Your saved research is unchanged. Retry the dashboard read,
                    then check the local application logs if it happens again.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-5"
                    onClick={() => router.reload({ only: ['dashboard'] })}
                >
                    <RefreshCw aria-hidden="true" />
                    Retry dashboard
                </Button>
            </CardContent>
        </Card>
    );
}
