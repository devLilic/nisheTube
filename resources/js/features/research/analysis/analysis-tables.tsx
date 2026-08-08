import { ExternalLink, ImageOff, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type {
    LibraryContext,
    ResearchChannelAnalysis,
    ResearchVideoAnalysis,
} from '@/types';
import {
    formatAnalysisTimestamp,
    formatDecimal,
    formatDuration,
    formatInteger,
    formatPercent,
} from './analysis-format';
import {
    externalYouTubeLinkProps,
    videoPreviewAvailable,
    youtubeChannelUrl,
    youtubeVideoUrl,
} from './youtube-links';

type Selection =
    | { type: 'video'; item: ResearchVideoAnalysis }
    | { type: 'channel'; item: ResearchChannelAnalysis };

const PAGE_SIZE = 8;

function DetailItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-b py-3 last:border-0">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-semibold break-words tabular-nums">
                {value}
            </dd>
        </div>
    );
}

function VideoPreview({
    video,
    variant,
}: {
    video: ResearchVideoAnalysis;
    variant: 'row' | 'drawer';
}) {
    const [failed, setFailed] = useState(false);
    const hasPreview = videoPreviewAvailable(video.thumbnail_url, failed);

    return (
        <a
            href={youtubeVideoUrl(video.provider_video_id)}
            {...externalYouTubeLinkProps}
            aria-label={`Preview for ${video.title}; open video on YouTube in a new tab`}
            className={`group/preview relative block shrink-0 overflow-hidden rounded-lg border bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none ${
                variant === 'drawer' ? 'aspect-video w-full' : 'h-16 w-28'
            }`}
        >
            {hasPreview ? (
                <img
                    src={video.thumbnail_url ?? undefined}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    referrerPolicy="no-referrer"
                    onError={() => setFailed(true)}
                    className="size-full object-cover transition-transform group-hover/preview:scale-[1.03]"
                />
            ) : (
                <span className="flex size-full flex-col items-center justify-center gap-1 bg-muted px-2 text-center text-[10px] leading-3 text-muted-foreground">
                    <ImageOff className="size-4" aria-hidden="true" />
                    Preview unavailable
                </span>
            )}
            <span className="absolute right-1.5 bottom-1.5 rounded bg-background/90 p-1 text-foreground opacity-0 shadow-sm transition-opacity group-hover/preview:opacity-100 group-focus-visible/preview:opacity-100">
                <ExternalLink className="size-3.5" aria-hidden="true" />
            </span>
        </a>
    );
}

function DetailDrawer({
    selection,
    timezone,
    onClose,
}: {
    selection: Selection | null;
    timezone: string;
    onClose: () => void;
}) {
    const item = selection?.item;

    return (
        <Dialog
            open={selection !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="top-0 right-0 left-auto h-dvh max-w-full translate-x-0 translate-y-0 content-start overflow-y-auto rounded-none p-6 sm:max-w-md">
                {selection && item && (
                    <>
                        <DialogHeader className="pr-8">
                            <div className="mb-2">
                                <Badge variant="outline">
                                    {selection.type === 'video'
                                        ? 'Video'
                                        : 'Channel'}{' '}
                                    details
                                </Badge>
                            </div>
                            <DialogTitle className="text-xl leading-7">
                                {item.title}
                            </DialogTitle>
                            <DialogDescription>
                                Exact immutable values captured for this
                                research run.
                            </DialogDescription>
                        </DialogHeader>

                        {selection.type === 'video' ? (
                            <>
                                <VideoPreview
                                    key={selection.item.provider_video_id}
                                    video={selection.item}
                                    variant="drawer"
                                />
                                <Button
                                    variant="outline"
                                    asChild
                                    className="w-fit"
                                >
                                    <a
                                        href={youtubeVideoUrl(
                                            selection.item.provider_video_id,
                                        )}
                                        {...externalYouTubeLinkProps}
                                    >
                                        Open on YouTube
                                        <ExternalLink aria-hidden="true" />
                                    </a>
                                </Button>
                                <dl>
                                    <DetailItem
                                        label="Channel"
                                        value={selection.item.channel_title}
                                    />
                                    <DetailItem
                                        label="Result rank"
                                        value={formatInteger(
                                            selection.item.result_rank,
                                        )}
                                    />
                                    <DetailItem
                                        label="Views"
                                        value={formatInteger(
                                            selection.item.view_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Views per day"
                                        value={formatDecimal(
                                            selection.item.views_per_day,
                                            6,
                                        )}
                                    />
                                    <DetailItem
                                        label="Likes"
                                        value={formatInteger(
                                            selection.item.like_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Comments"
                                        value={formatInteger(
                                            selection.item.comment_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Engagement rate"
                                        value={formatPercent(
                                            selection.item.engagement_rate,
                                            4,
                                        )}
                                    />
                                    <DetailItem
                                        label="Reach ratio"
                                        value={formatDecimal(
                                            selection.item.reach_ratio,
                                            8,
                                        )}
                                    />
                                    <DetailItem
                                        label="Duration"
                                        value={formatDuration(
                                            selection.item.duration_seconds,
                                        )}
                                    />
                                    <DetailItem
                                        label="Published"
                                        value={formatAnalysisTimestamp(
                                            selection.item.published_at,
                                            timezone,
                                        )}
                                    />
                                    <DetailItem
                                        label="Collected"
                                        value={formatAnalysisTimestamp(
                                            selection.item.collected_at,
                                            timezone,
                                        )}
                                    />
                                </dl>
                            </>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    asChild
                                    className="w-fit"
                                >
                                    <a
                                        href={youtubeChannelUrl(
                                            selection.item.provider_channel_id,
                                        )}
                                        {...externalYouTubeLinkProps}
                                    >
                                        Open on YouTube
                                        <ExternalLink aria-hidden="true" />
                                    </a>
                                </Button>
                                <dl>
                                    <DetailItem
                                        label="Handle"
                                        value={
                                            selection.item.custom_url ??
                                            'Not available'
                                        }
                                    />
                                    <DetailItem
                                        label="Country"
                                        value={
                                            selection.item.country?.toUpperCase() ??
                                            'Not available'
                                        }
                                    />
                                    <DetailItem
                                        label="Subscribers"
                                        value={
                                            selection.item
                                                .subscriber_count_hidden
                                                ? 'Hidden by channel'
                                                : formatInteger(
                                                      selection.item
                                                          .subscriber_count,
                                                  )
                                        }
                                    />
                                    <DetailItem
                                        label="Lifetime views"
                                        value={formatInteger(
                                            selection.item.view_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Lifetime videos"
                                        value={formatInteger(
                                            selection.item.video_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Lifetime uploads per month"
                                        value={formatDecimal(
                                            selection.item
                                                .lifetime_uploads_per_month,
                                            2,
                                        )}
                                    />
                                    <DetailItem
                                        label="Videos in this sample"
                                        value={formatInteger(
                                            selection.item.sample_video_count,
                                        )}
                                    />
                                    <DetailItem
                                        label="Median sample views"
                                        value={formatInteger(
                                            selection.item.median_views,
                                        )}
                                    />
                                    <DetailItem
                                        label="Median sample views per day"
                                        value={formatDecimal(
                                            selection.item.median_views_per_day,
                                            6,
                                        )}
                                    />
                                    <DetailItem
                                        label="Median reach ratio"
                                        value={formatDecimal(
                                            selection.item.median_reach_ratio,
                                            8,
                                        )}
                                    />
                                    <DetailItem
                                        label="Collected"
                                        value={formatAnalysisTimestamp(
                                            selection.item.collected_at,
                                            timezone,
                                        )}
                                    />
                                </dl>
                            </>
                        )}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

export function AnalysisTables({
    videos,
    channels,
    timezone,
    library,
}: {
    videos: ResearchVideoAnalysis[];
    channels: ResearchChannelAnalysis[];
    timezone: string;
    library: LibraryContext;
}) {
    const [mode, setMode] = useState<'videos' | 'channels'>('videos');
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [selection, setSelection] = useState<Selection | null>(null);
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filteredVideos = useMemo(
        () =>
            videos.filter((video) =>
                `${video.title} ${video.channel_title}`
                    .toLocaleLowerCase()
                    .includes(normalizedQuery),
            ),
        [normalizedQuery, videos],
    );
    const filteredChannels = useMemo(
        () =>
            channels.filter((channel) =>
                `${channel.title} ${channel.custom_url ?? ''}`
                    .toLocaleLowerCase()
                    .includes(normalizedQuery),
            ),
        [channels, normalizedQuery],
    );
    const count =
        mode === 'videos' ? filteredVideos.length : filteredChannels.length;
    const pageCount = Math.max(Math.ceil(count / PAGE_SIZE), 1);

    const firstIndex = (page - 1) * PAGE_SIZE;
    const visibleVideos = filteredVideos.slice(
        firstIndex,
        firstIndex + PAGE_SIZE,
    );
    const visibleChannels = filteredChannels.slice(
        firstIndex,
        firstIndex + PAGE_SIZE,
    );

    return (
        <section aria-labelledby="analysis-table-title">
            <Card>
                <CardHeader className="gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle id="analysis-table-title">
                            Detailed analysis
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Search all captured rows, inspect exact values, and
                            open a row for its immutable snapshot.
                        </CardDescription>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                        <div
                            className="inline-flex rounded-lg border bg-muted/40 p-1"
                            aria-label="Analysis table type"
                        >
                            {(['videos', 'channels'] as const).map((type) => (
                                <button
                                    key={type}
                                    type="button"
                                    aria-pressed={mode === type}
                                    onClick={() => {
                                        setMode(type);
                                        setPage(1);
                                    }}
                                    className="flex-1 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors aria-pressed:bg-background aria-pressed:shadow-sm"
                                >
                                    {type}
                                </button>
                            ))}
                        </div>
                        <label className="relative block sm:w-72">
                            <span className="sr-only">Search {mode}</span>
                            <Search
                                className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                value={query}
                                onChange={(event) => {
                                    setQuery(event.target.value);
                                    setPage(1);
                                }}
                                placeholder={`Search ${mode}...`}
                                className="pl-9"
                            />
                        </label>
                    </div>
                </CardHeader>
                <CardContent className="px-0">
                    {count === 0 ? (
                        <div className="border-y border-dashed px-6 py-12 text-center">
                            <p className="text-sm font-medium">
                                No matching {mode}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Try a channel name, title, or handle.
                            </p>
                        </div>
                    ) : mode === 'videos' ? (
                        <Table>
                            <caption className="sr-only">
                                All enriched videos; textual alternative to the
                                video chart.
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Video</TableHead>
                                    <TableHead>Channel</TableHead>
                                    <TableHead className="text-right">
                                        Views
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Views/day
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Engagement
                                    </TableHead>
                                    <TableHead>
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleVideos.map((video) => (
                                    <TableRow key={video.provider_video_id}>
                                        <TableCell className="max-w-96 min-w-64">
                                            <div className="flex items-start gap-3">
                                                <VideoPreview
                                                    video={video}
                                                    variant="row"
                                                />
                                                <div className="min-w-0 pt-0.5">
                                                    <a
                                                        href={youtubeVideoUrl(
                                                            video.provider_video_id,
                                                        )}
                                                        {...externalYouTubeLinkProps}
                                                        className="group/link inline-flex max-w-full items-start gap-1 font-medium hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        title={video.title}
                                                    >
                                                        <span className="line-clamp-2 break-words group-hover/link:underline">
                                                            {video.title}
                                                        </span>
                                                        <ExternalLink
                                                            className="mt-0.5 size-3.5 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="sr-only">
                                                            Opens in a new tab
                                                        </span>
                                                    </a>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Rank {video.result_rank}{' '}
                                                        ·{' '}
                                                        {formatDuration(
                                                            video.duration_seconds,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-64 min-w-44">
                                            <a
                                                href={youtubeChannelUrl(
                                                    video.channel_id,
                                                )}
                                                {...externalYouTubeLinkProps}
                                                className="group/link inline-flex max-w-full items-start gap-1 hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                title={video.channel_title}
                                            >
                                                <span className="line-clamp-2 break-words group-hover/link:underline">
                                                    {video.channel_title}
                                                </span>
                                                <ExternalLink
                                                    className="mt-0.5 size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">
                                                    Opens channel in a new tab
                                                </span>
                                            </a>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatInteger(video.view_count)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatDecimal(
                                                video.views_per_day,
                                                2,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatPercent(
                                                video.engagement_rate,
                                                2,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <FavoriteToggle
                                                    library={library}
                                                    targetType="video"
                                                    targetReference={
                                                        video.provider_video_id
                                                    }
                                                    label={video.title}
                                                    compact
                                                />
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setSelection({
                                                            type: 'video',
                                                            item: video,
                                                        })
                                                    }
                                                >
                                                    Details
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Table>
                            <caption className="sr-only">
                                All captured channels; textual alternative to
                                the channel chart.
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Channel</TableHead>
                                    <TableHead className="text-right">
                                        Subscribers
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Sample videos
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Median views/day
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Median reach
                                    </TableHead>
                                    <TableHead>
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleChannels.map((channel) => (
                                    <TableRow key={channel.provider_channel_id}>
                                        <TableCell className="max-w-96 min-w-64">
                                            <a
                                                href={youtubeChannelUrl(
                                                    channel.provider_channel_id,
                                                )}
                                                {...externalYouTubeLinkProps}
                                                className="group/link inline-flex max-w-full items-start gap-1 font-medium hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                title={channel.title}
                                            >
                                                <span className="line-clamp-2 break-words group-hover/link:underline">
                                                    {channel.title}
                                                </span>
                                                <ExternalLink
                                                    className="mt-0.5 size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">
                                                    Opens in a new tab
                                                </span>
                                            </a>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {channel.custom_url ??
                                                    channel.provider_channel_id}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {channel.subscriber_count_hidden
                                                ? 'Hidden'
                                                : formatInteger(
                                                      channel.subscriber_count,
                                                  )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatInteger(
                                                channel.sample_video_count,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatDecimal(
                                                channel.median_views_per_day,
                                                2,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatDecimal(
                                                channel.median_reach_ratio,
                                                2,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <FavoriteToggle
                                                    library={library}
                                                    targetType="channel"
                                                    targetReference={
                                                        channel.provider_channel_id
                                                    }
                                                    label={channel.title}
                                                    compact
                                                />
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setSelection({
                                                            type: 'channel',
                                                            item: channel,
                                                        })
                                                    }
                                                >
                                                    Details
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    {count > 0 && (
                        <div className="flex flex-col gap-3 border-t px-6 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-xs text-muted-foreground"
                                role="status"
                            >
                                Showing {firstIndex + 1}–
                                {Math.min(firstIndex + PAGE_SIZE, count)} of{' '}
                                {count} {mode}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page === 1}
                                    onClick={() =>
                                        setPage((value) =>
                                            Math.max(value - 1, 1),
                                        )
                                    }
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page === pageCount}
                                    onClick={() =>
                                        setPage((value) =>
                                            Math.min(value + 1, pageCount),
                                        )
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <DetailDrawer
                selection={selection}
                timezone={timezone}
                onClose={() => setSelection(null)}
            />
        </section>
    );
}
