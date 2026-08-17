import { AlertTriangle, BarChart3, GitCompareArrows } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ResearchEvidenceProfile as EvidenceProfile,
    ResearchRobustSample,
} from '@/types';

function number(value: number | null | undefined, digits = 1) {
    return value == null
        ? 'Not available'
        : new Intl.NumberFormat('en-US', {
              maximumFractionDigits: digits,
          }).format(value);
}

function percent(value: number | null | undefined) {
    return value == null ? 'Not available' : `${number(value * 100, 1)}%`;
}

function SampleCard({
    title,
    sample,
}: {
    title: string;
    sample: ResearchRobustSample;
}) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center justify-between gap-3">
                <h4 className="font-medium">{title}</h4>
                <Badge variant="outline">{sample.count} results</Badge>
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm xl:grid-cols-3">
                <div>
                    <dt className="text-muted-foreground">Metric coverage</dt>
                    <dd className="font-medium tabular-nums">
                        {sample.metric_count}/{sample.count}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Median views/day</dt>
                    <dd className="font-medium tabular-nums">
                        {number(sample.median_views_per_day)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">P25–P75</dt>
                    <dd className="font-medium tabular-nums">
                        {number(sample.p25_views_per_day)}–
                        {number(sample.p75_views_per_day)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">P90</dt>
                    <dd className="font-medium tabular-nums">
                        {number(sample.p90_views_per_day)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Trimmed mean</dt>
                    <dd className="font-medium tabular-nums">
                        {number(sample.trimmed_mean_views_per_day)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Robust state</dt>
                    <dd className="font-medium capitalize">{sample.state}</dd>
                </div>
            </dl>
        </div>
    );
}

export function ResearchEvidenceProfile({
    profile,
}: {
    profile: EvidenceProfile | undefined;
}) {
    if (!profile) {
        return null;
    }

    if (profile.state !== 'available') {
        return (
            <Card aria-labelledby="research-evidence-profile-title">
                <CardHeader>
                    <CardTitle id="research-evidence-profile-title">
                        Evidence quality
                    </CardTitle>
                    <CardDescription>{profile.description}</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const fullOutliers = profile.outlier_evidence?.full;
    const strictOutliers = profile.outlier_evidence?.strict;
    const stability = profile.stability;

    return (
        <section aria-labelledby="research-evidence-profile-title">
            <Card>
                <CardHeader className="border-b">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="research-evidence-profile-title">
                                Evidence quality
                            </CardTitle>
                            <CardDescription className="mt-1 max-w-3xl">
                                {profile.description} Shorts and long-form are
                                reported independently; no cross-format winner
                                is claimed.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">{profile.version}</Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5 pt-6">
                    {profile.warnings.length > 0 && (
                        <Alert>
                            <AlertTriangle aria-hidden="true" />
                            <AlertTitle>Evidence limitations</AlertTitle>
                            <AlertDescription>
                                <ul className="list-disc space-y-1 pl-5">
                                    {profile.warnings.map((warning) => (
                                        <li key={warning}>{warning}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}

                    {profile.sample_evidence && (
                        <div className="grid gap-4 xl:grid-cols-2">
                            <SampleCard
                                title="Complete returned sample"
                                sample={profile.sample_evidence.full}
                            />
                            <SampleCard
                                title="Strictly relevant sample"
                                sample={profile.sample_evidence.strict}
                            />
                        </div>
                    )}

                    {profile.format_evidence && (
                        <div>
                            <h3 className="flex items-center gap-2 font-semibold">
                                <BarChart3
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Format-specific evidence
                            </h3>
                            <div className="mt-3 grid gap-4 xl:grid-cols-2">
                                <SampleCard
                                    title="Shorts"
                                    sample={profile.format_evidence.shorts}
                                />
                                <SampleCard
                                    title="Long-form"
                                    sample={profile.format_evidence.long_form}
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 xl:grid-cols-2">
                        <div className="rounded-lg border p-4">
                            <h3 className="font-semibold">
                                Outlier resistance — complete sample
                            </h3>
                            {fullOutliers?.state === 'available' ? (
                                <>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Top-video dependency:{' '}
                                        <strong className="text-foreground capitalize">
                                            {fullOutliers.dependency}
                                        </strong>{' '}
                                        ({percent(fullOutliers.top_video_share)}{' '}
                                        of measured views/day total).
                                    </p>
                                    <div className="mt-3 overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <caption className="sr-only">
                                                Values before and after removing
                                                the largest outliers
                                            </caption>
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="py-2 pr-3">
                                                        Removed
                                                    </th>
                                                    <th className="py-2 pr-3 text-right">
                                                        Median
                                                    </th>
                                                    <th className="py-2 text-right">
                                                        Mean
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {fullOutliers.removals.map(
                                                    (row) => (
                                                        <tr
                                                            key={
                                                                row.removed_top_count
                                                            }
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-2 pr-3">
                                                                Top{' '}
                                                                {
                                                                    row.removed_top_count
                                                                }
                                                            </td>
                                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                                {number(
                                                                    row.median_views_per_day,
                                                                )}
                                                            </td>
                                                            <td className="py-2 text-right tabular-nums">
                                                                {number(
                                                                    row.mean_views_per_day,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    At least three public views/day values are
                                    required.
                                </p>
                            )}
                            <details className="mt-4 border-t pt-3">
                                <summary className="cursor-pointer font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                    Strict-sample outlier evidence
                                </summary>
                                {strictOutliers?.state === 'available' ? (
                                    <div className="mt-3 overflow-x-auto">
                                        <p className="mb-2 text-sm text-muted-foreground">
                                            Top-video dependency:{' '}
                                            <strong className="text-foreground capitalize">
                                                {strictOutliers.dependency}
                                            </strong>{' '}
                                            (
                                            {percent(
                                                strictOutliers.top_video_share,
                                            )}
                                            ).
                                        </p>
                                        <table className="w-full text-left text-sm">
                                            <caption className="sr-only">
                                                Strictly relevant values before
                                                and after removing the largest
                                                outliers
                                            </caption>
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="py-2 pr-3">
                                                        Removed
                                                    </th>
                                                    <th className="py-2 pr-3 text-right">
                                                        Median
                                                    </th>
                                                    <th className="py-2 text-right">
                                                        Mean
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {strictOutliers.removals.map(
                                                    (row) => (
                                                        <tr
                                                            key={
                                                                row.removed_top_count
                                                            }
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-2 pr-3">
                                                                Top{' '}
                                                                {
                                                                    row.removed_top_count
                                                                }
                                                            </td>
                                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                                {number(
                                                                    row.median_views_per_day,
                                                                )}
                                                            </td>
                                                            <td className="py-2 text-right tabular-nums">
                                                                {number(
                                                                    row.mean_views_per_day,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        At least three strictly relevant results
                                        with public views/day are required.
                                    </p>
                                )}
                            </details>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h3 className="flex items-center gap-2 font-semibold">
                                <GitCompareArrows
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Compatible-snapshot stability
                            </h3>
                            {stability.state === 'available' ? (
                                <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Stability
                                        </dt>
                                        <dd className="font-medium capitalize">
                                            {stability.label}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Video overlap
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {percent(stability.result_overlap)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Channel overlap
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {percent(stability.channel_overlap)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Order stability
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {number(
                                                stability.order_stability,
                                                2,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Median metric variance
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {percent(stability.metric_variance)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Median variation
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {percent(
                                                stability.median_variation,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {stability.reason}
                                </p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>
    );
}
