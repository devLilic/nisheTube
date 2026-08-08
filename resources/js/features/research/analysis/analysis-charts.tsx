import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ResearchChannelAnalysis, ResearchVideoAnalysis } from '@/types';
import { formatDecimal, formatInteger } from './analysis-format';

type BarDatum = {
    id: string;
    label: string;
    value: number;
    formattedValue: string;
};

function HorizontalBars({ data, label }: { data: BarDatum[]; label: string }) {
    const maximum = Math.max(...data.map((item) => item.value), 1);

    if (data.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                No comparable values are available for this chart.
            </p>
        );
    }

    return (
        <ol aria-label={label} className="space-y-4">
            {data.map((item, index) => (
                <li
                    key={item.id}
                    className="grid grid-cols-[1.5rem_minmax(0,1fr)] gap-3"
                >
                    <span className="pt-0.5 text-right text-xs font-semibold text-muted-foreground tabular-nums">
                        {index + 1}
                    </span>
                    <div className="min-w-0">
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span
                                className="truncate font-medium"
                                title={item.label}
                            >
                                {item.label}
                            </span>
                            <span className="shrink-0 font-semibold tabular-nums">
                                {item.formattedValue}
                            </span>
                        </div>
                        <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full min-w-1 rounded-full bg-primary"
                                style={{
                                    width: `${(item.value / maximum) * 100}%`,
                                }}
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </li>
            ))}
        </ol>
    );
}

export function AnalysisCharts({
    videos,
    channels,
}: {
    videos: ResearchVideoAnalysis[];
    channels: ResearchChannelAnalysis[];
}) {
    const videoData = videos
        .filter((video) => video.views_per_day !== null)
        .toSorted(
            (left, right) =>
                (right.views_per_day ?? 0) - (left.views_per_day ?? 0),
        )
        .slice(0, 7)
        .map((video) => ({
            id: video.provider_video_id,
            label: video.title,
            value: video.views_per_day ?? 0,
            formattedValue: `${formatDecimal(video.views_per_day)}/day`,
        }));
    const channelData = channels
        .filter((channel) => channel.subscriber_count !== null)
        .toSorted(
            (left, right) =>
                (right.subscriber_count ?? 0) - (left.subscriber_count ?? 0),
        )
        .slice(0, 7)
        .map((channel) => ({
            id: channel.provider_channel_id,
            label: channel.title,
            value: channel.subscriber_count ?? 0,
            formattedValue: formatInteger(channel.subscriber_count),
        }));

    return (
        <section
            aria-label="Analysis charts"
            className="grid gap-6 xl:grid-cols-2"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Video performance</CardTitle>
                    <CardDescription>
                        Top enriched videos by views per day. Exact values and
                        the complete text alternative are available in the video
                        table below.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <HorizontalBars
                        data={videoData}
                        label="Top videos by views per day"
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Channel reach</CardTitle>
                    <CardDescription>
                        Channels with the largest visible subscriber audience.
                        Hidden counts remain excluded, never treated as zero.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <HorizontalBars
                        data={channelData}
                        label="Top channels by subscribers"
                    />
                </CardContent>
            </Card>
        </section>
    );
}
