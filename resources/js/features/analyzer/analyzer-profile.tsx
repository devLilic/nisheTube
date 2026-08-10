import { ExternalLink, ImageOff } from 'lucide-react';
import { useState } from 'react';
import { MetricHint } from '@/components/metric-hint';
import { PartialDataBanner } from '@/components/partial-data-banner';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnalyzerRun } from '@/types';

const integers = new Intl.NumberFormat('en-US');

function value(number: number | null, suffix = '') {
    return number === null
        ? 'Unavailable'
        : `${integers.format(number)}${suffix}`;
}

function decimal(number: number | null, suffix = '') {
    return number === null
        ? 'Unavailable'
        : `${number.toLocaleString('en-US', { maximumFractionDigits: 3 })}${suffix}`;
}

function timestamp(input: string | null, timezone: string) {
    return input
        ? new Intl.DateTimeFormat('en', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: timezone,
          }).format(new Date(input))
        : 'Unavailable';
}

function ageDays(seconds: number) {
    return Math.floor(seconds / 86_400);
}

function Metric({
    label,
    value,
    provenance,
    explanation,
    accent = false,
}: {
    label: string;
    value: string;
    provenance: 'YouTube Data' | 'Calculated Metrics';
    explanation: string;
    accent?: boolean;
}) {
    return (
        <div
            className={
                accent
                    ? 'rounded-xl border border-primary/20 bg-primary/[0.055] p-3.5'
                    : 'rounded-xl border bg-muted/20 p-3.5'
            }
        >
            <div className="flex items-center gap-1">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <MetricHint label={label}>{explanation}</MetricHint>
            </div>
            <p className="mt-2 text-xl font-semibold break-words tabular-nums">
                {value}
            </p>
            <Badge variant="outline" className="mt-3 text-[10px]">
                {provenance}
            </Badge>
        </div>
    );
}

export function AnalyzerProfile({
    run,
    timezone,
    section = 'video',
}: {
    run: AnalyzerRun;
    timezone: string;
    section?: 'video' | 'channel';
}) {
    const [videoImageFailed, setVideoImageFailed] = useState(false);
    const [channelImageFailed, setChannelImageFailed] = useState(false);

    if (!run.channel) {
        return null;
    }

    const { video, channel, metrics } = run;

    return (
        <div className="space-y-6">
            {section === 'video' && video && (
                <>
                    <Card>
                        <CardHeader className="gap-4 md:flex-row md:items-start">
                            <div className="aspect-video w-full shrink-0 overflow-hidden rounded-xl border bg-muted md:w-64">
                                {video.thumbnail_url && !videoImageFailed ? (
                                    <img
                                        src={video.thumbnail_url}
                                        alt=""
                                        className="size-full object-cover"
                                        referrerPolicy="no-referrer"
                                        onError={() =>
                                            setVideoImageFailed(true)
                                        }
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center gap-2 text-sm text-muted-foreground">
                                        <ImageOff className="size-5" /> Preview
                                        unavailable
                                    </div>
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        Video Profile
                                    </Badge>
                                    <Badge variant="outline">
                                        Official category:{' '}
                                        {video.category?.name ??
                                            (video.category
                                                ? `Category ${video.category.id}`
                                                : 'Category unavailable')}
                                    </Badge>
                                </div>
                                <CardTitle className="mt-3 text-xl leading-7 break-words">
                                    {video.title}
                                </CardTitle>
                                <CardDescription className="mt-2">
                                    Published{' '}
                                    {timestamp(video.published_at, timezone)}
                                </CardDescription>
                                <a
                                    href={video.youtube_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Open on YouTube{' '}
                                    <ExternalLink className="size-4" />
                                </a>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-xl border bg-muted/25 p-3.5 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {video.source_mode} observation
                                    </Badge>
                                    <Badge variant="outline">
                                        YouTube Data
                                    </Badge>
                                </div>
                                <p className="mt-3 leading-6 text-muted-foreground">
                                    Observed{' '}
                                    {timestamp(video.observed_at, timezone)}.
                                    First seen for your account{' '}
                                    {timestamp(video.first_seen_at, timezone)}.
                                    Cached values retain their original
                                    observation time.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <section aria-labelledby="video-performance-heading">
                        <div className="mb-3 flex items-center gap-1.5">
                            <h2
                                id="video-performance-heading"
                                className="text-lg font-semibold"
                            >
                                Video Performance
                            </h2>
                            <MetricHint label="Video performance">
                                Public YouTube totals and derived ratios pinned
                                to this analysis attempt. Derived values do not
                                represent YouTube search volume.
                            </MetricHint>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Metric
                                label="Views"
                                value={value(video.view_count)}
                                provenance="YouTube Data"
                                explanation="The public lifetime view total observed for this video at the pinned observation time."
                                accent
                            />
                            <Metric
                                label="Likes"
                                value={value(video.like_count)}
                                provenance="YouTube Data"
                                explanation="The public like total observed for this video."
                            />
                            <Metric
                                label="Comments"
                                value={value(video.comment_count)}
                                provenance="YouTube Data"
                                explanation="The public top-level comment total reported by YouTube for this video."
                            />
                            <Metric
                                label="Lifetime Average Views/Day"
                                value={decimal(
                                    metrics?.lifetime_views_per_day ?? null,
                                )}
                                provenance="Calculated Metrics"
                                explanation="Lifetime views divided by video age in days. This normalizes for age but is not current velocity."
                                accent
                            />
                            <Metric
                                label="Views / subscribers"
                                value={decimal(
                                    metrics?.views_to_subscribers_ratio ?? null,
                                    'x',
                                )}
                                provenance="Calculated Metrics"
                                explanation="Video views divided by the author's public subscriber count at observation time."
                                accent
                            />
                            <Metric
                                label="Like rate"
                                value={decimal(
                                    metrics?.like_rate_percent ?? null,
                                    '%',
                                )}
                                provenance="Calculated Metrics"
                                explanation="Public likes as a percentage of observed lifetime views."
                            />
                            <Metric
                                label="Comment rate"
                                value={decimal(
                                    metrics?.comment_rate_percent ?? null,
                                    '%',
                                )}
                                provenance="Calculated Metrics"
                                explanation="Public comments as a percentage of observed lifetime views."
                            />
                            <Metric
                                label="Public engagement proxy"
                                value={decimal(
                                    metrics?.public_engagement_rate_percent ??
                                        null,
                                    '%',
                                )}
                                provenance="Calculated Metrics"
                                explanation="Public likes plus comments as a percentage of observed lifetime views."
                            />
                        </div>
                        {metrics && (
                            <Card className="mt-4 shadow-none">
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                Channel-relative performance
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                Anchor views compared with the
                                                frozen recent author-channel
                                                cohort. The anchor is excluded
                                                from its baseline.
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline">
                                            {metrics.breakout_class?.label ??
                                                'Classification unavailable'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <div className="rounded-xl border border-primary/20 bg-primary/[0.055] p-3.5">
                                            <dt className="flex items-center gap-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Versus channel median
                                                <MetricHint label="Versus channel median">
                                                    Anchor video views divided
                                                    by the median views of the
                                                    frozen recent channel
                                                    cohort.
                                                </MetricHint>
                                            </dt>
                                            <dd className="mt-2 font-semibold tabular-nums">
                                                {decimal(
                                                    metrics.channel_median_ratio,
                                                    'x',
                                                )}
                                            </dd>
                                        </div>
                                        <div className="rounded-xl border border-primary/20 bg-primary/[0.055] p-3.5">
                                            <dt className="flex items-center gap-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Versus channel average
                                                <MetricHint label="Versus channel average">
                                                    Anchor video views divided
                                                    by the average views of the
                                                    frozen recent channel
                                                    cohort.
                                                </MetricHint>
                                            </dt>
                                            <dd className="mt-2 font-semibold tabular-nums">
                                                {decimal(
                                                    metrics.channel_average_ratio,
                                                    'x',
                                                )}
                                            </dd>
                                        </div>
                                        <div className="rounded-xl border bg-muted/20 p-3.5">
                                            <dt className="flex items-center gap-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Recent rank
                                                <MetricHint label="Recent rank">
                                                    The anchor's raw-view
                                                    position among the
                                                    comparable recent channel
                                                    uploads.
                                                </MetricHint>
                                            </dt>
                                            <dd className="mt-2 font-semibold tabular-nums">
                                                {metrics.recent_rank === null
                                                    ? 'Unavailable'
                                                    : `${metrics.recent_rank} of ${metrics.recent_comparison_count}`}
                                            </dd>
                                        </div>
                                        <div className="rounded-xl border bg-muted/20 p-3.5">
                                            <dt className="flex items-center gap-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Empirical percentile
                                                <MetricHint label="Empirical percentile">
                                                    The percentage of comparable
                                                    recent uploads at or below
                                                    the anchor's view total.
                                                </MetricHint>
                                            </dt>
                                            <dd className="mt-2 font-semibold tabular-nums">
                                                {decimal(
                                                    metrics.recent_percentile,
                                                    '%',
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        Raw-view class shown with age context:{' '}
                                        <strong className="text-foreground">
                                            {ageDays(metrics.age_seconds)} days
                                        </strong>{' '}
                                        old and{' '}
                                        <strong className="text-foreground">
                                            {decimal(
                                                metrics.lifetime_views_per_day,
                                            )}{' '}
                                            Lifetime Average Views/Day
                                        </strong>
                                        . Thresholds:{' '}
                                        {metrics.threshold_version ??
                                            'unavailable'}
                                        .
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {metrics && (
                            <p className="mt-3 text-xs text-muted-foreground">
                                Calculated{' '}
                                {timestamp(metrics.calculated_at, timezone)}
                                {' · '}
                                {metrics.calculation_version}. Lifetime Average
                                Views/Day is age-normalized lifetime
                                performance, not current velocity.
                            </p>
                        )}
                        {metrics?.warnings.map((warning) => (
                            <PartialDataBanner
                                key={warning}
                                title="Calculated metric unavailable"
                                description={warning}
                            />
                        ))}
                    </section>
                </>
            )}

            {section === 'channel' && (
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <CardTitle>
                                    {video
                                        ? 'Author Channel Profile'
                                        : 'Channel Profile'}
                                </CardTitle>
                                <CardDescription className="mt-1">
                                    Public channel identity and the observation
                                    pinned to this analysis.
                                </CardDescription>
                            </div>
                            <Badge variant="outline">YouTube Data</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-[auto_minmax(0,1fr)]">
                        <div className="size-20 overflow-hidden rounded-xl border bg-muted">
                            {channel.thumbnail_url && !channelImageFailed ? (
                                <img
                                    src={channel.thumbnail_url}
                                    alt=""
                                    className="size-full object-cover"
                                    referrerPolicy="no-referrer"
                                    onError={() => setChannelImageFailed(true)}
                                />
                            ) : (
                                <ImageOff className="m-7 size-6 text-muted-foreground" />
                            )}
                        </div>
                        <div>
                            <a
                                href={channel.youtube_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 text-lg font-semibold break-words hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {channel.title}{' '}
                                <ExternalLink className="size-4 shrink-0" />
                            </a>
                            {video && (
                                <a
                                    href={`/analyzer?channel=${encodeURIComponent(channel.provider_channel_id)}`}
                                    className="mt-3 inline-flex text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Analyze this channel directly
                                </a>
                            )}
                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                                <div className="rounded-lg border border-primary/20 bg-primary/[0.055] p-3">
                                    <dt className="flex items-center gap-1 text-muted-foreground">
                                        Subscribers
                                        <MetricHint label="Subscribers">
                                            The author's public subscriber total
                                            at the pinned channel observation
                                            time.
                                        </MetricHint>
                                    </dt>
                                    <dd className="mt-1 font-medium tabular-nums">
                                        {channel.subscriber_count_hidden
                                            ? 'Hidden by channel'
                                            : value(channel.subscriber_count)}
                                    </dd>
                                </div>
                                <div className="rounded-lg border border-primary/20 bg-primary/[0.055] p-3">
                                    <dt className="flex items-center gap-1 text-muted-foreground">
                                        Lifetime views
                                        <MetricHint label="Channel lifetime views">
                                            The channel's public lifetime view
                                            total at the pinned observation
                                            time.
                                        </MetricHint>
                                    </dt>
                                    <dd className="mt-1 font-medium tabular-nums">
                                        {value(channel.view_count)}
                                    </dd>
                                </div>
                                <div className="rounded-lg border bg-muted/20 p-3">
                                    <dt className="text-muted-foreground">
                                        Public videos
                                    </dt>
                                    <dd className="mt-1 font-medium tabular-nums">
                                        {value(channel.video_count)}
                                    </dd>
                                </div>
                                <div className="rounded-lg border bg-muted/20 p-3">
                                    <dt className="text-muted-foreground">
                                        Country
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {channel.country ?? 'Unavailable'}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-4 text-xs text-muted-foreground">
                                Observed{' '}
                                {timestamp(channel.observed_at, timezone)}.
                                First seen{' '}
                                {timestamp(channel.first_seen_at, timezone)}.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
