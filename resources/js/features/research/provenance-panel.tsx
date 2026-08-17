import {
    AlertTriangle,
    CheckCircle2,
    CircleOff,
    Database,
    LoaderCircle,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { ResearchProvenance, ResearchRun } from '@/types';

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

function formatParameter(value: string | null) {
    if (!value || value === 'any') {
        return 'Any';
    }

    return value.replaceAll('_', ' ');
}

export function ProvenancePanel({
    provenance,
    timezone,
    queryText,
    marketName,
    parameters,
    formulaVersion,
}: {
    provenance: ResearchProvenance | undefined;
    timezone: string;
    queryText: string;
    marketName: string;
    parameters: ResearchRun['parameters'];
    formulaVersion: string | null;
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
        <Sheet>
            <Card>
                <CardHeader>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                <Database
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div>
                                <CardTitle>Evidence provenance</CardTitle>
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
                <CardContent className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {source.video_observation_count.toLocaleString('en-US')}{' '}
                        video and{' '}
                        {source.channel_observation_count.toLocaleString(
                            'en-US',
                        )}{' '}
                        channel observations ·{' '}
                        {source.freshness_state === 'cached'
                            ? 'cached'
                            : source.freshness_state === 'mixed'
                              ? 'fresh and cached'
                              : 'fresh'}
                    </p>
                    <SheetTrigger asChild>
                        <Button variant="outline">Inspect provenance</Button>
                    </SheetTrigger>
                </CardContent>
            </Card>

            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <SheetHeader className="border-b pr-12">
                    <SheetTitle>Research provenance</SheetTitle>
                    <SheetDescription>
                        Provider, frozen query, parameters, endpoints,
                        observation window, cache policy, formula, and immutable
                        snapshot coverage.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-6 px-4 pb-8">
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

                    <section aria-labelledby="provenance-boundary">
                        <h3 id="provenance-boundary" className="font-semibold">
                            Evidence boundaries
                        </h3>
                        <div className="mt-3 grid gap-3">
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
                                        <p className="font-medium">
                                            {group.label}
                                        </p>
                                    </div>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {group.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section aria-labelledby="provenance-request">
                        <h3 id="provenance-request" className="font-semibold">
                            Provider and frozen request
                        </h3>
                        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            <ProvenanceField
                                label="Provider"
                                value={source.provider}
                            />
                            <ProvenanceField
                                label="Market"
                                value={marketName}
                            />
                            <ProvenanceField label="Query" value={queryText} />
                            <ProvenanceField
                                label="Order"
                                value={formatParameter(parameters.search_order)}
                            />
                            <ProvenanceField
                                label="Language"
                                value={parameters.language.toUpperCase()}
                            />
                            <ProvenanceField
                                label="Format lens"
                                value={formatParameter(
                                    parameters.content_format,
                                )}
                            />
                            <ProvenanceField
                                label="Duration"
                                value={formatParameter(
                                    parameters.video_duration,
                                )}
                            />
                            <ProvenanceField
                                label="Channel-size lens"
                                value={formatParameter(
                                    parameters.target_channel_size,
                                )}
                            />
                            <ProvenanceField
                                label="Published after"
                                value={
                                    parameters.published_after ??
                                    'No lower bound'
                                }
                            />
                            <ProvenanceField
                                label="Published before"
                                value={
                                    parameters.published_before ??
                                    'No upper bound'
                                }
                            />
                        </dl>
                    </section>

                    <section aria-labelledby="provenance-endpoints">
                        <h3 id="provenance-endpoints" className="font-semibold">
                            Provider endpoints
                        </h3>
                        {source.endpoints.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                No endpoint ledger rows are linked to this
                                collection source.
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y rounded-xl border text-sm">
                                {source.endpoints.map((endpoint) => (
                                    <li
                                        key={`${endpoint.endpoint}-${endpoint.quota_bucket}`}
                                        className="flex items-center justify-between gap-4 p-3"
                                    >
                                        <div>
                                            <p className="font-mono font-medium">
                                                {endpoint.endpoint}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {endpoint.quota_bucket}
                                            </p>
                                        </div>
                                        <span className="text-right tabular-nums">
                                            {endpoint.request_count} request
                                            {endpoint.request_count === 1
                                                ? ''
                                                : 's'}{' '}
                                            · {endpoint.estimated_cost} cost
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section aria-labelledby="provenance-snapshots">
                        <h3 id="provenance-snapshots" className="font-semibold">
                            Observation window, cache, formula, and snapshots
                        </h3>
                        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            <ProvenanceField
                                label="Observation from"
                                value={formatTimestamp(
                                    source.observed_from,
                                    timezone,
                                )}
                            />
                            <ProvenanceField
                                label="Observation to"
                                value={formatTimestamp(
                                    source.observed_to,
                                    timezone,
                                )}
                            />
                            <ProvenanceField
                                label="Cache policy"
                                value={source.cache_policy.replaceAll('_', ' ')}
                            />
                            <ProvenanceField
                                label="Observation freshness"
                                value={source.freshness_state.replaceAll(
                                    '_',
                                    ' ',
                                )}
                            />
                            <ProvenanceField
                                label="Freshness window"
                                value={`${Math.round(source.freshness_window_seconds / 3600)} hours`}
                            />
                            <ProvenanceField
                                label="Formula version"
                                value={
                                    formulaVersion ??
                                    'No score formula persisted'
                                }
                            />
                            <ProvenanceField
                                label="Video snapshots"
                                value={`${source.pinned_video_count} of ${source.result_count} pinned`}
                            />
                            <ProvenanceField
                                label="Channel snapshots"
                                value={`${source.pinned_channel_count} of ${source.result_count} pinned`}
                            />
                            <ProvenanceField
                                label="Fresh / cached"
                                value={`${source.fresh_observation_count} / ${source.cached_observation_count}`}
                            />
                            <ProvenanceField
                                label="Provider attempts"
                                value={`${source.quota_attempt_count} recorded · ${source.quota_estimated_cost} estimated cost`}
                            />
                            <ProvenanceField
                                label="Collection source"
                                value={source.public_id}
                                breakAll
                            />
                        </dl>
                        {source.cached_observation_count > 0 && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                {
                                    'Cached observations retain their original observed time.'
                                }
                            </p>
                        )}
                    </section>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ProvenanceField({
    label,
    value,
    breakAll = false,
}: {
    label: string;
    value: string;
    breakAll?: boolean;
}) {
    return (
        <div className="rounded-lg border p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={`mt-1 font-medium capitalize ${breakAll ? 'break-all' : 'break-words'}`}
            >
                {value}
            </dd>
        </div>
    );
}
