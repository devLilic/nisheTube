import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ImageOff,
    Images,
    LoaderCircle,
    RefreshCw,
    ScanSearch,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatNumber } from '@/lib/formatters';
import type { AnalyzerRun, AnalyzerThumbnailItem } from '@/types';

export function ThumbnailAnalysis({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const analysis = run.thumbnail_analysis;
    const profile = analysis?.profile;
    const form = useForm({});

    if (!analysis) {
        return null;
    }

    const submit = () =>
        form.post(`/analyzer/runs/${run.public_id}/thumbnails`, {
            preserveScroll: true,
        });

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Images className="size-5" /> Thumbnail patterns
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Optional inferred visual features and observed
                        within-cohort performance associations. Loading this
                        section never starts the analysis job.
                    </p>
                </div>
                {analysis.can_analyze && (
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={form.processing}
                        aria-busy={form.processing}
                    >
                        {form.processing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : profile?.status === 'failed' ? (
                            <RefreshCw />
                        ) : (
                            <ScanSearch />
                        )}
                        {profile?.status === 'failed'
                            ? 'Retry thumbnail analysis'
                            : 'Analyze thumbnail patterns'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-5">
                {analysis.status === 'not_requested' && (
                    <Alert>
                        <Images />
                        <AlertTitle>Thumbnail analysis is off</AlertTitle>
                        <AlertDescription>
                            Start the explicit queued action to fetch the stored
                            cohort thumbnail URLs and calculate local visual
                            features. It uses no YouTube Data API quota and
                            stores no raw image bytes.
                        </AlertDescription>
                    </Alert>
                )}

                {analysis.is_active && profile && (
                    <Alert aria-busy="true">
                        <LoaderCircle className="animate-spin" />
                        <AlertTitle>
                            {profile.status === 'queued'
                                ? 'Thumbnail analysis queued'
                                : 'Analyzing thumbnails'}
                        </AlertTitle>
                        <AlertDescription>
                            {profile.processed_image_count} image result(s)
                            persisted from {profile.cohort_video_count} recent
                            cohort video(s). The page refreshes while this
                            owner-scoped job runs.
                        </AlertDescription>
                    </Alert>
                )}

                {profile?.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Thumbnail analysis failed</AlertTitle>
                        <AlertDescription>
                            {profile.error_message ??
                                'The queued analysis failed safely.'}{' '}
                            Existing Analyzer metrics and stored thumbnail URLs
                            are unchanged. Retry creates a new immutable
                            attempt.
                        </AlertDescription>
                    </Alert>
                )}

                {profile &&
                    !analysis.is_active &&
                    profile.status !== 'failed' && (
                        <>
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">
                                    Detected analysis
                                </Badge>
                                <Badge variant="outline" className="capitalize">
                                    {profile.status}
                                </Badge>
                                <Badge variant="outline">
                                    Confidence{' '}
                                    {profile.confidence_score === null
                                        ? 'unavailable'
                                        : `${Math.round(profile.confidence_score)}%`}
                                </Badge>
                                <Badge variant="outline">
                                    {profile.available_image_count} available
                                </Badge>
                                <Badge variant="outline">
                                    {profile.reused_image_count} cached
                                </Badge>
                                <Badge variant="outline">
                                    {profile.unavailable_image_count}{' '}
                                    unavailable
                                </Badge>
                            </div>

                            {profile.status === 'insufficient' && (
                                <Alert>
                                    <ImageOff />
                                    <AlertTitle>
                                        Insufficient comparable thumbnails
                                    </AlertTitle>
                                    <AlertDescription>
                                        Feature evidence remains visible, but no
                                        cluster reached the frozen minimum
                                        sample for performance claims.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {profile.warnings.map((warning) => (
                                <Alert key={warning}>
                                    <AlertTriangle />
                                    <AlertTitle>
                                        Partial thumbnail evidence
                                    </AlertTitle>
                                    <AlertDescription>
                                        {warning}
                                    </AlertDescription>
                                </Alert>
                            ))}

                            <ThumbnailFeatureGrid items={profile.items} />
                            <ThumbnailAssociationTable
                                aggregates={profile.aggregates}
                            />

                            <Alert>
                                <ScanSearch />
                                <AlertTitle>
                                    Observed association, not causation
                                </AlertTitle>
                                <AlertDescription>
                                    These values compare visual clusters only
                                    inside this pinned recent-video cohort. They
                                    do not show that a color, brightness, or
                                    composition caused views or Breakout
                                    performance and do not alter opportunity
                                    scoring.
                                </AlertDescription>
                            </Alert>
                        </>
                    )}

                {profile && (
                    <p className="text-xs text-muted-foreground">
                        Inferred provenance · Provider {profile.provider} ·
                        Feature version {profile.algorithm_version} ·
                        Association version {profile.calculation_version} ·
                        Minimum cluster sample {profile.minimum_sample_size} ·
                        Attempt {profile.attempt_number}
                        {profile.calculated_at
                            ? ` · Calculated ${formatDate(profile.calculated_at, timezone)}`
                            : ''}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function ThumbnailFeatureGrid({ items }: { items: AnalyzerThumbnailItem[] }) {
    if (items.length === 0) {
        return (
            <Alert>
                <ImageOff />
                <AlertTitle>No thumbnail evidence saved yet</AlertTitle>
                <AlertDescription>
                    The queued job has not persisted an image result.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <section className="space-y-3">
            <h3 className="text-sm font-semibold">Exact visual features</h3>
            <div className="grid gap-3 lg:grid-cols-2">
                {items.map((item) => (
                    <article
                        key={`${item.role}-${item.provider_video_id}`}
                        className="overflow-hidden rounded-lg border"
                    >
                        <div className="flex gap-3 p-3">
                            {item.status === 'available' &&
                            item.thumbnail_url ? (
                                <img
                                    src={item.thumbnail_url}
                                    alt=""
                                    loading="lazy"
                                    className="aspect-video w-36 rounded-md border object-cover"
                                />
                            ) : (
                                <div className="flex aspect-video w-36 items-center justify-center rounded-md border bg-muted">
                                    <ImageOff className="size-5" />
                                </div>
                            )}
                            <div className="min-w-0 space-y-2">
                                <p className="text-sm font-medium break-words">
                                    {item.title}
                                </p>
                                <div className="flex flex-wrap gap-1">
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {item.role.replaceAll('_', ' ')}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {item.status}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {item.cache_status}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        {item.status === 'available' ? (
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 border-t p-3 text-sm sm:grid-cols-3">
                                <Feature
                                    label="Dominant color"
                                    value={item.dominant_color}
                                />
                                <Feature
                                    label="Brightness"
                                    value={featureValue(
                                        item.average_brightness,
                                        item.brightness_class,
                                    )}
                                />
                                <Feature
                                    label="Saturation"
                                    value={featureValue(
                                        item.average_saturation,
                                        item.saturation_class,
                                    )}
                                />
                                <Feature
                                    label="Contrast"
                                    value={featureValue(
                                        item.contrast_score,
                                        item.contrast_class,
                                    )}
                                />
                                <Feature
                                    label="Edge density"
                                    value={featureValue(
                                        item.edge_density,
                                        item.composition_class,
                                    )}
                                />
                                <Feature
                                    label="Dimensions"
                                    value={
                                        item.width && item.height
                                            ? `${item.width} × ${item.height}`
                                            : null
                                    }
                                />
                            </dl>
                        ) : (
                            <p className="border-t p-3 text-sm text-muted-foreground">
                                Inaccessible image evidence (
                                {item.error_code ?? 'unknown safe error'}).
                                NisheTube does not guess whether the remote
                                image was removed or restricted.
                            </p>
                        )}
                    </article>
                ))}
            </div>
        </section>
    );
}

function Feature({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium capitalize">{value ?? 'Unavailable'}</dd>
        </div>
    );
}

function featureValue(value: number | null, classification: string | null) {
    return value === null
        ? null
        : `${value.toFixed(1)}%${classification ? ` · ${classification}` : ''}`;
}

function ThumbnailAssociationTable({
    aggregates,
}: {
    aggregates: NonNullable<
        NonNullable<AnalyzerRun['thumbnail_analysis']>['profile']
    >['aggregates'];
}) {
    if (aggregates.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3">
            <div>
                <h3 className="text-sm font-semibold">
                    Visual cluster performance association
                </h3>
                <p className="text-xs text-muted-foreground">
                    Exact cohort values remain null below the frozen minimum
                    sample.
                </p>
            </div>
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="p-3">Cluster</th>
                            <th className="p-3">Videos</th>
                            <th className="p-3">Median views</th>
                            <th className="p-3">Average views</th>
                            <th className="p-3">Median lifetime views/day</th>
                            <th className="p-3">Breakout rate</th>
                            <th className="p-3">Evidence IDs</th>
                        </tr>
                    </thead>
                    <tbody>
                        {aggregates.map((aggregate) => (
                            <tr
                                key={aggregate.cluster_key}
                                className="border-t align-top"
                            >
                                <td className="p-3 font-medium">
                                    {aggregate.label}
                                    {!aggregate.meets_minimum_sample && (
                                        <Badge
                                            variant="outline"
                                            className="mt-1 block w-fit"
                                        >
                                            Below minimum
                                        </Badge>
                                    )}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {aggregate.sample_count}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {formatNullable(aggregate.median_views)}
                                    <small className="block text-muted-foreground">
                                        n={aggregate.view_sample_count}
                                    </small>
                                </td>
                                <td className="p-3 tabular-nums">
                                    {formatNullable(aggregate.average_views)}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {formatNullable(
                                        aggregate.median_views_per_day,
                                    )}
                                    <small className="block text-muted-foreground">
                                        n={aggregate.views_per_day_sample_count}
                                    </small>
                                </td>
                                <td className="p-3 tabular-nums">
                                    {aggregate.breakout_rate_percent === null
                                        ? 'Insufficient'
                                        : `${aggregate.breakout_rate_percent.toFixed(1)}%`}
                                    <small className="block text-muted-foreground">
                                        {aggregate.breakout_count}/
                                        {aggregate.breakout_sample_count}
                                    </small>
                                </td>
                                <td className="p-3 break-all">
                                    {aggregate.evidence_video_ids.join(', ')}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function formatNullable(value: number | null) {
    return value === null ? 'Insufficient' : formatNumber(value);
}
