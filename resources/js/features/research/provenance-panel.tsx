import {
    AlertTriangle,
    CheckCircle2,
    CircleOff,
    Database,
    LoaderCircle,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ResearchProvenance } from '@/types';

function formatTimestamp(value: string | null, timezone: string) {
    if (!value) {
        return 'Unavailable';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

export function ProvenancePanel({
    provenance,
    timezone,
}: {
    provenance: ResearchProvenance | undefined;
    timezone: string;
}) {
    if (!provenance || provenance.state === 'loading') {
        return (
            <Card aria-busy="true">
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <LoaderCircle
                            className="mt-0.5 size-5 animate-spin text-primary"
                            aria-hidden="true"
                        />
                        <div>
                            <CardTitle>Observation provenance</CardTitle>
                            <CardDescription className="mt-1">
                                Collection source context is being prepared.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
            </Card>
        );
    }

    if (!provenance.source) {
        return (
            <Alert variant="destructive">
                <AlertTriangle aria-hidden="true" />
                <AlertTitle>Source context unavailable</AlertTitle>
                <AlertDescription>{provenance.message}</AlertDescription>
            </Alert>
        );
    }

    const { source } = provenance;

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Database className="size-5" aria-hidden="true" />
                        </div>
                        <div>
                            <CardTitle>Observation provenance</CardTitle>
                            <CardDescription className="mt-1 max-w-3xl">
                                {provenance.message}
                            </CardDescription>
                        </div>
                    </div>
                    <Badge
                        variant={
                            provenance.state === 'partial' ||
                            provenance.state === 'error'
                                ? 'destructive'
                                : 'outline'
                        }
                        className="w-fit capitalize"
                    >
                        {provenance.state === 'ready'
                            ? 'Sources pinned'
                            : provenance.state}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {(provenance.state === 'partial' ||
                    provenance.state === 'error' ||
                    provenance.state === 'empty') && (
                    <Alert
                        variant={
                            provenance.state === 'error'
                                ? 'destructive'
                                : 'default'
                        }
                    >
                        {provenance.state === 'empty' ? (
                            <CircleOff aria-hidden="true" />
                        ) : (
                            <AlertTriangle aria-hidden="true" />
                        )}
                        <AlertTitle>
                            {provenance.state === 'empty'
                                ? 'No reusable observations'
                                : provenance.state === 'partial'
                                  ? 'Partial source coverage'
                                  : 'Collection source failed'}
                        </AlertTitle>
                        <AlertDescription>
                            {provenance.message}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-3 md:grid-cols-2">
                    {source.groups.map((group) => (
                        <div
                            key={group.key}
                            className="rounded-xl border bg-muted/25 p-4"
                        >
                            <div className="flex items-center gap-2">
                                <CheckCircle2
                                    className="size-4 text-success"
                                    aria-hidden="true"
                                />
                                <p className="font-medium">{group.label}</p>
                            </div>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {group.description}
                            </p>
                        </div>
                    ))}
                </div>

                <dl className="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Provider</dt>
                        <dd className="mt-1 font-medium capitalize">
                            {source.provider}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Observation window
                        </dt>
                        <dd className="mt-1 font-medium">
                            {formatTimestamp(source.observed_from, timezone)}
                            {source.observed_to &&
                                source.observed_to !== source.observed_from && (
                                    <>
                                        {' '}
                                        to{' '}
                                        {formatTimestamp(
                                            source.observed_to,
                                            timezone,
                                        )}
                                    </>
                                )}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Video sources pinned
                        </dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {source.pinned_video_count.toLocaleString('en-US')}{' '}
                            of {source.result_count.toLocaleString('en-US')}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Channel sources pinned
                        </dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {source.pinned_channel_count.toLocaleString(
                                'en-US',
                            )}{' '}
                            of {source.result_count.toLocaleString('en-US')}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Immutable observations
                        </dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {source.video_observation_count.toLocaleString(
                                'en-US',
                            )}{' '}
                            videos ·{' '}
                            {source.channel_observation_count.toLocaleString(
                                'en-US',
                            )}{' '}
                            channels
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Collection context
                        </dt>
                        <dd className="mt-1 font-medium">
                            {source.historical_backfill
                                ? 'Historical Research source'
                                : 'Research source'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Observation freshness
                        </dt>
                        <dd className="mt-1 font-medium">
                            {source.freshness_state === 'fresh'
                                ? 'Fresh observations'
                                : source.freshness_state === 'cached'
                                  ? 'Cached observations'
                                  : source.freshness_state === 'mixed'
                                    ? 'Fresh and cached observations'
                                    : 'No observations'}
                        </dd>
                        {source.cached_observation_count > 0 && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {source.cached_observation_count.toLocaleString(
                                    'en-US',
                                )}{' '}
                                cached sources retain their original observed
                                time.
                            </p>
                        )}
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Cache policy</dt>
                        <dd className="mt-1 font-medium">
                            {source.cache_policy === 'fresh_only'
                                ? 'Fresh capture'
                                : source.cache_policy === 'force_refresh'
                                  ? 'Force refresh'
                                  : source.freshness_window_seconds > 0
                                    ? `Reuse within ${Math.round(source.freshness_window_seconds / 3600)} hours`
                                    : source.cache_policy.replaceAll('_', ' ')}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Provider attempts
                        </dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {source.quota_attempt_count.toLocaleString('en-US')}{' '}
                            calls ·{' '}
                            {source.quota_estimated_cost.toLocaleString(
                                'en-US',
                            )}{' '}
                            estimated units
                        </dd>
                    </div>
                </dl>

                <p className="text-xs break-all text-muted-foreground">
                    Collection source {source.public_id}
                </p>
            </CardContent>
        </Card>
    );
}
