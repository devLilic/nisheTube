import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    GitCompareArrows,
    LoaderCircle,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CrossChannelComparisonView } from '@/features/analyzer/cross-channel-comparison';
import { formatDate } from '@/lib/formatters';
import type {
    AnalyzerComparisonOption,
    Auth,
    CrossChannelComparison,
} from '@/types';

const selectionLabels = ['First channel', 'Second channel', 'Third channel'];

export default function AnalyzerCompare({
    options,
    selected,
    selection,
    auth,
}: {
    options: AnalyzerComparisonOption[];
    selected: CrossChannelComparison | null;
    selection: { runs: string[] };
    auth: Auth;
}) {
    const eligibleIds = new Set(
        options.flatMap((option) =>
            option.attempts.map((attempt) => attempt.public_id),
        ),
    );
    const [selectedIds, setSelectedIds] = useState(
        selection.runs.filter((id) => eligibleIds.has(id)).slice(0, 3),
    );
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const selectedChannels = selectedIds.map((id) =>
        options.find((option) =>
            option.attempts.some((attempt) => attempt.public_id === id),
        ),
    );
    const selectedChannelIds = new Set(
        selectedChannels.flatMap((option) =>
            option === undefined ? [] : [option.channel_id],
        ),
    );
    const filteredOptions = useMemo(() => {
        const normalized = query.trim().toLocaleLowerCase();

        return normalized === ''
            ? options
            : options.filter(
                  (option) =>
                      option.channel_title
                          .toLocaleLowerCase()
                          .includes(normalized) ||
                      option.provider_channel_id
                          .toLocaleLowerCase()
                          .includes(normalized),
              );
    }, [options, query]);
    const canCompare = selectedIds.length >= 2 && selectedIds.length <= 3;

    const addChannel = (option: AnalyzerComparisonOption) => {
        if (
            selectedIds.length === 3 ||
            selectedChannelIds.has(option.channel_id)
        ) {
            return;
        }

        setSelectedIds([...selectedIds, option.attempts[0].public_id]);
    };

    const removeChannel = (index: number) => {
        setSelectedIds(
            selectedIds.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const compare = () => {
        if (!canCompare) {
            return;
        }

        setLoading(true);
        router.get(
            '/analyzer/compare',
            {
                before: selectedIds[0],
                after: selectedIds[1],
                third: selectedIds[2] ?? undefined,
            },
            { preserveState: true, onFinish: () => setLoading(false) },
        );
    };

    return (
        <>
            <Head title="Cross-channel comparison" />
            <PageContainer>
                <PageHeader
                    eyebrow="Stored Analyzer evidence"
                    title="Cross-channel comparison"
                    description="Compare exact stored evidence for two or three channels without creating a score or recommendation."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/analyzer">
                                <ArrowLeft /> Back to Analyzer
                            </Link>
                        </Button>
                    }
                />
                <AnalyticsGlossary page="analyzer_compare" />

                <Card>
                    <CardHeader>
                        <CardTitle>Select two or three channels</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Channels are ordered alphabetically. Each channel
                            groups up to five recent immutable attempts; the
                            newest attempt is selected first.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {options.length < 2 ? (
                            <StatePanel
                                title="Not enough channel analyses"
                                description="Complete analyses for at least two different channels before comparing stored cohorts."
                                action={
                                    <Button asChild>
                                        <Link href="/analyzer">
                                            Analyze a channel
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <>
                                <section aria-labelledby="selected-channels-title">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h2
                                                id="selected-channels-title"
                                                className="font-semibold"
                                            >
                                                Comparison set
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {selectedIds.length} of 3
                                                channels selected. At least two
                                                are required.
                                            </p>
                                        </div>
                                        <Button
                                            onClick={compare}
                                            disabled={!canCompare || loading}
                                        >
                                            {loading ? (
                                                <LoaderCircle className="animate-spin" />
                                            ) : (
                                                <GitCompareArrows />
                                            )}
                                            {loading
                                                ? 'Loading comparison'
                                                : `Compare ${selectedIds.length || ''} channels`}
                                        </Button>
                                    </div>

                                    <div className="mt-4 grid gap-3 lg:grid-cols-3">
                                        {selectedIds.map((id, index) => {
                                            const option =
                                                selectedChannels[index];

                                            if (option === undefined) {
                                                return null;
                                            }

                                            return (
                                                <article
                                                    key={option.channel_id}
                                                    className="rounded-xl border border-primary/25 bg-primary/[0.04] p-4"
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <p className="text-xs font-medium tracking-wide text-primary uppercase">
                                                                {
                                                                    selectionLabels[
                                                                        index
                                                                    ]
                                                                }
                                                            </p>
                                                            <h3 className="truncate font-semibold">
                                                                {
                                                                    option.channel_title
                                                                }
                                                            </h3>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            aria-label={`Remove ${option.channel_title}`}
                                                            onClick={() =>
                                                                removeChannel(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <X />
                                                        </Button>
                                                    </div>
                                                    <div className="mt-3 space-y-2">
                                                        <Label
                                                            htmlFor={`comparison-attempt-${option.channel_id}`}
                                                        >
                                                            Analysis attempt
                                                        </Label>
                                                        <select
                                                            id={`comparison-attempt-${option.channel_id}`}
                                                            value={id}
                                                            onChange={(event) =>
                                                                setSelectedIds(
                                                                    selectedIds.map(
                                                                        (
                                                                            selectedId,
                                                                            selectedIndex,
                                                                        ) =>
                                                                            selectedIndex ===
                                                                            index
                                                                                ? event
                                                                                      .target
                                                                                      .value
                                                                                : selectedId,
                                                                    ),
                                                                )
                                                            }
                                                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            {option.attempts.map(
                                                                (attempt) => (
                                                                    <option
                                                                        key={
                                                                            attempt.public_id
                                                                        }
                                                                        value={
                                                                            attempt.public_id
                                                                        }
                                                                    >
                                                                        {formatDate(
                                                                            attempt.observed_at,
                                                                            auth
                                                                                .user
                                                                                .timezone,
                                                                        )}{' '}
                                                                        · n=
                                                                        {
                                                                            attempt.cohort_video_count
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </div>
                                                </article>
                                            );
                                        })}
                                        {Array.from({
                                            length: 3 - selectedIds.length,
                                        }).map((_, index) => (
                                            <div
                                                key={`empty-${index}`}
                                                className="flex min-h-32 items-center justify-center rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
                                            >
                                                Add a channel from the ordered
                                                list below
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                <section
                                    aria-labelledby="available-channels-title"
                                    className="border-t pt-5"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div>
                                            <h2
                                                id="available-channels-title"
                                                className="font-semibold"
                                            >
                                                Available channels
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {options.length} recent
                                                channels, ordered A–Z
                                            </p>
                                        </div>
                                        <div className="relative w-full sm:max-w-sm">
                                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                value={query}
                                                onChange={(event) =>
                                                    setQuery(event.target.value)
                                                }
                                                placeholder="Filter channels by title or ID"
                                                aria-label="Filter available channels"
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>

                                    {filteredOptions.length === 0 ? (
                                        <StatePanel
                                            title="No matching channels"
                                            description="Try a different title or YouTube channel ID."
                                        />
                                    ) : (
                                        <div className="mt-4 max-h-[32rem] divide-y overflow-y-auto rounded-xl border">
                                            {filteredOptions.map((option) => {
                                                const isSelected =
                                                    selectedChannelIds.has(
                                                        option.channel_id,
                                                    );
                                                const latest =
                                                    option.attempts[0];

                                                return (
                                                    <article
                                                        key={option.channel_id}
                                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <h3 className="font-medium">
                                                                    {
                                                                        option.channel_title
                                                                    }
                                                                </h3>
                                                                <Badge variant="outline">
                                                                    {
                                                                        option.attempt_count
                                                                    }{' '}
                                                                    {option.attempt_count ===
                                                                    1
                                                                        ? 'attempt'
                                                                        : 'attempts'}
                                                                </Badge>
                                                            </div>
                                                            <p className="mt-1 text-xs break-all text-muted-foreground">
                                                                {
                                                                    option.provider_channel_id
                                                                }
                                                            </p>
                                                            <p className="mt-2 text-sm text-muted-foreground">
                                                                Latest observed{' '}
                                                                {formatDate(
                                                                    latest.observed_at,
                                                                    auth.user
                                                                        .timezone,
                                                                )}{' '}
                                                                · n=
                                                                {
                                                                    latest.cohort_video_count
                                                                }
                                                            </p>
                                                            <div className="mt-2 flex flex-wrap gap-2">
                                                                {latest.has_topic_performance ? (
                                                                    <Badge variant="secondary">
                                                                        Topic
                                                                        data
                                                                    </Badge>
                                                                ) : null}
                                                                {latest.has_thumbnail_performance ? (
                                                                    <Badge variant="secondary">
                                                                        Thumbnail
                                                                        data
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant={
                                                                isSelected
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                            disabled={
                                                                isSelected ||
                                                                selectedIds.length ===
                                                                    3
                                                            }
                                                            onClick={() =>
                                                                addChannel(
                                                                    option,
                                                                )
                                                            }
                                                        >
                                                            {isSelected ? (
                                                                <Check />
                                                            ) : (
                                                                <Plus />
                                                            )}
                                                            {isSelected
                                                                ? 'Selected'
                                                                : 'Add channel'}
                                                        </Button>
                                                    </article>
                                                );
                                            })}
                                        </div>
                                    )}
                                </section>
                            </>
                        )}
                    </CardContent>
                </Card>

                {selected === null && options.length >= 2 ? (
                    <StatePanel
                        title="Choose channels to compare"
                        description="Select two or three different channels, confirm each immutable attempt, then compare exact metrics and compatibility warnings."
                        icon={GitCompareArrows}
                    />
                ) : selected !== null ? (
                    <CrossChannelComparisonView
                        comparison={selected}
                        timezone={auth.user.timezone}
                    />
                ) : null}
            </PageContainer>
        </>
    );
}

AnalyzerCompare.layout = {
    breadcrumbs: [
        { title: 'Analyzer', href: '/analyzer' },
        { title: 'Compare channels', href: '/analyzer/compare' },
    ],
};
