import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Binoculars,
    Bookmark,
    Boxes,
    Filter,
    FlaskConical,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { AnalyticsGlossary } from '@/components/analytics-glossary';
import { StatePanel } from '@/components/data-state';
import { MarketBadge } from '@/components/market-badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import type { PaginationMeta } from '@/components/pagination-controls';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { WorkspaceHandoff } from '@/features/integration/workspace-handoff';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type {
    Auth,
    ExploreEntityType,
    ExploreFilters,
    ExploreResult,
    MarketKey,
    LibraryContext,
} from '@/types';

type PageProps = {
    auth: Auth;
    errors?: Record<string, string>;
    filters: ExploreFilters;
    results: PaginationMeta & { data: ExploreResult[] };
    counts: Record<ExploreEntityType, number>;
    markets: { key: MarketKey; name: string }[];
    categories: { category_id: string; name: string }[];
    capabilities: {
        provider_io_during_browsing: false;
        watchlist_available: boolean;
        workspace_available: boolean;
    };
    workspaces: WorkspaceOption[];
    library: LibraryContext;
};

const entityLabels: Record<ExploreEntityType, string> = {
    video: 'Videos',
    channel: 'Channels',
    candidate: 'Candidates',
};

export default function ExploreIndex(props: PageProps) {
    const [draft, setDraft] = useState<ExploreFilters>(props.filters);
    const [loading, setLoading] = useState(false);
    const partialCount = useMemo(
        () => props.results.data.filter((item) => item.partial).length,
        [props.results.data],
    );

    useEffect(() => {
        const stopStart = router.on('start', () => setLoading(true));
        const stopFinish = router.on('finish', () => setLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    const visit = (filters: ExploreFilters, page = 1) => {
        router.get('/explore', compactQuery(filters, page), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const selectEntity = (entityType: ExploreEntityType) => {
        const next = { ...draft, entity_type: entityType };
        setDraft(next);
        visit(next);
    };

    const clearFilters = () => {
        const next = defaultFilters(draft.entity_type);

        setDraft(next);
        visit(next);
    };

    return (
        <>
            <Head title="Explore" />
            <PageContainer>
                <PageHeader
                    eyebrow="Your stored research graph"
                    title="Explore observed evidence"
                    description="Filter videos, channels, and discovery candidates already stored in your account. Browsing and filtering use no YouTube API quota."
                />
                <AnalyticsGlossary page="explore" />

                <div
                    className="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Evidence type"
                >
                    {(Object.keys(entityLabels) as ExploreEntityType[]).map(
                        (entityType) => (
                            <Button
                                key={entityType}
                                type="button"
                                role="tab"
                                aria-selected={draft.entity_type === entityType}
                                variant={
                                    draft.entity_type === entityType
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => selectEntity(entityType)}
                            >
                                {entityLabels[entityType]}
                                <Badge
                                    variant="secondary"
                                    className="ml-1 tabular-nums"
                                >
                                    {props.counts[entityType]}
                                </Badge>
                            </Button>
                        ),
                    )}
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="size-4" />
                            Stored-evidence filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit(draft);
                            }}
                        >
                            <Field label="Topic or title">
                                <Input
                                    value={draft.topic ?? ''}
                                    placeholder="e.g. home studio"
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            topic: event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Source workflow">
                                <Select
                                    value={draft.source}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            source: value as ExploreFilters['source'],
                                        })
                                    }
                                    options={[
                                        ['all', 'All stored sources'],
                                        ['research', 'Search / Research'],
                                        ['analyzer', 'Analyzer'],
                                        ['discovery', 'Discovery'],
                                        ['library', 'Library'],
                                    ]}
                                />
                            </Field>
                            <Field label="Market">
                                <Select
                                    value={draft.market ?? ''}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            market:
                                                (value as MarketKey) || null,
                                        })
                                    }
                                    options={[
                                        ['', 'Any known market'],
                                        ...props.markets.map(
                                            (market) =>
                                                [market.key, market.name] as [
                                                    string,
                                                    string,
                                                ],
                                        ),
                                    ]}
                                />
                            </Field>
                            <Field label="Official category">
                                <Select
                                    value={draft.category ?? ''}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            category: value || null,
                                        })
                                    }
                                    options={[
                                        ['', 'Any known category'],
                                        ...props.categories.map(
                                            (category) =>
                                                [
                                                    category.category_id,
                                                    category.name,
                                                ] as [string, string],
                                        ),
                                    ]}
                                />
                            </Field>
                            <Field label="Breakout class">
                                <Select
                                    value={draft.breakout ?? ''}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            breakout:
                                                (value as ExploreFilters['breakout']) ||
                                                null,
                                        })
                                    }
                                    options={[
                                        ['', 'Any / unavailable'],
                                        ['normal', 'Normal'],
                                        ['strong', 'Strong (3×+)'],
                                        ['breakout', 'Breakout (>5×)'],
                                    ]}
                                />
                            </Field>
                            <Field label="Channel size">
                                <Select
                                    value={draft.channel_size ?? ''}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            channel_size:
                                                (value as ExploreFilters['channel_size']) ||
                                                null,
                                        })
                                    }
                                    options={[
                                        ['', 'Any / unavailable'],
                                        ['small', 'Small (<10K)'],
                                        ['mid', 'Mid (10K–99K)'],
                                        ['large', 'Large (100K+)'],
                                        ['hidden', 'Hidden / unavailable'],
                                    ]}
                                />
                            </Field>
                            <Field label="Minimum score">
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={draft.min_score ?? ''}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            min_score: numeric(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Minimum confidence">
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={draft.min_confidence ?? ''}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            min_confidence: numeric(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Minimum observed performance">
                                <Input
                                    type="number"
                                    min="0"
                                    placeholder="Views/day or median views"
                                    value={draft.min_performance ?? ''}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            min_performance: numeric(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Observed from">
                                <Input
                                    type="date"
                                    value={draft.observed_from ?? ''}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            observed_from:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Observed to">
                                <Input
                                    type="date"
                                    value={draft.observed_to ?? ''}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            observed_to:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                            <Field label="Organization">
                                <Select
                                    value={draft.organization}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            organization:
                                                value as ExploreFilters['organization'],
                                        })
                                    }
                                    options={[
                                        ['all', 'Any state'],
                                        ['favorite', 'Favorited'],
                                        ['not_favorite', 'Not favorited'],
                                        ['curated', 'Curated'],
                                        ['unreviewed', 'Unreviewed'],
                                    ]}
                                />
                            </Field>
                            <Field label="Sort">
                                <Select
                                    value={draft.sort}
                                    onChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            sort: value as ExploreFilters['sort'],
                                        })
                                    }
                                    options={[
                                        ['latest', 'Latest evidence'],
                                        ['score_desc', 'Highest score'],
                                        [
                                            'performance_desc',
                                            'Highest observed performance',
                                        ],
                                        ['title', 'Title A–Z'],
                                    ]}
                                />
                            </Field>
                            <div className="flex items-end gap-2 xl:col-span-3">
                                <Button type="submit" disabled={loading}>
                                    <Search className="size-4" />
                                    {loading ? 'Filtering…' : 'Apply filters'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={loading}
                                    onClick={clearFilters}
                                >
                                    Clear
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {props.errors && Object.keys(props.errors).length > 0 && (
                    <StatePanel
                        title="Explore filters could not be applied"
                        description={
                            Object.values(props.errors)[0] ??
                            'Review the bounded filter values and try again.'
                        }
                        icon={AlertTriangle}
                        tone="danger"
                        action={
                            <Button variant="outline" onClick={clearFilters}>
                                Reset filters
                            </Button>
                        }
                    />
                )}

                {partialCount > 0 && (
                    <div
                        className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm"
                        role="status"
                    >
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            {partialCount} result{partialCount === 1 ? '' : 's'}{' '}
                            have missing stored metrics. Missing values remain
                            unavailable; Explore does not replace them with zero
                            or fetch them automatically.
                        </p>
                    </div>
                )}

                {loading ? (
                    <div className="grid gap-4 lg:grid-cols-2" aria-busy="true">
                        {[0, 1, 2, 3].map((key) => (
                            <Card
                                key={key}
                                className="h-64 animate-pulse bg-muted/40"
                            />
                        ))}
                    </div>
                ) : props.results.data.length === 0 ? (
                    <StatePanel
                        title="No stored evidence matches"
                        description="Clear one or more filters, or create a Search, Analyzer, or Discovery result first. Filtering never starts provider collection."
                        icon={Binoculars}
                        action={
                            <Button variant="outline" onClick={clearFilters}>
                                Clear filters
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {props.results.data.map((item) => (
                            <EvidenceCard
                                key={`${item.entity_type}:${item.id}`}
                                item={item}
                                capabilities={props.capabilities}
                                timezone={props.auth.user.timezone}
                                workspaces={props.workspaces}
                                library={props.library}
                            />
                        ))}
                    </div>
                )}

                <PaginationControls
                    pagination={props.results}
                    onPageChange={(page) => visit(draft, page)}
                />
            </PageContainer>
        </>
    );
}

function EvidenceCard({
    item,
    capabilities,
    timezone,
    workspaces,
    library,
}: {
    item: ExploreResult;
    capabilities: PageProps['capabilities'];
    timezone: string;
    workspaces: WorkspaceOption[];
    library: LibraryContext;
}) {
    const validate = () => {
        if (
            item.entity_type !== 'candidate' ||
            !item.validate_url ||
            item.validate_url.startsWith('/research/')
        ) {
            return;
        }

        if (
            window.confirm(
                'Validate this candidate with a new queued Search? This is an explicit quota-aware action.',
            )
        ) {
            router.post(item.validate_url);
        }
    };

    return (
        <Card className="overflow-hidden">
            <CardContent className="space-y-4 p-5">
                <div className="flex gap-4">
                    {item.thumbnail_url ? (
                        <img
                            src={item.thumbnail_url}
                            alt=""
                            className="size-20 shrink-0 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="flex size-20 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <Boxes className="size-6" />
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">{item.entity_type}</Badge>
                            {item.favorite && (
                                <Badge variant="secondary">
                                    <Bookmark className="size-3" /> Favorite
                                </Badge>
                            )}
                            {item.breakout_class && (
                                <Badge>{item.breakout_class}</Badge>
                            )}
                        </div>
                        <h2
                            className="mt-2 line-clamp-2 font-semibold"
                            title={item.title}
                        >
                            {item.title}
                        </h2>
                        {item.subtitle && (
                            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                {item.subtitle}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {item.sources.map((source) => (
                        <Badge key={source} variant="outline">
                            {source}
                        </Badge>
                    ))}
                    {item.market && <MarketBadge market={item.market} />}
                    {item.category && (
                        <Badge variant="outline">
                            {item.category.name ??
                                `Category ${item.category.id}`}
                        </Badge>
                    )}
                    {item.research_status && (
                        <Badge variant="outline">
                            {item.research_status.replaceAll('_', ' ')}
                        </Badge>
                    )}
                    {item.detected_topic_profile && (
                        <Badge variant="outline">
                            Inferred: {item.detected_topic_profile.niche}
                        </Badge>
                    )}
                </div>

                <dl className="grid grid-cols-2 gap-3 rounded-lg bg-muted/40 p-3 text-sm sm:grid-cols-4">
                    <Metric label="Score" value={number(item.score, 1)} />
                    <Metric
                        label="Confidence"
                        value={number(item.confidence, 1)}
                    />
                    <Metric
                        label={
                            item.entity_type === 'channel'
                                ? 'Median views'
                                : 'Lifetime views/day'
                        }
                        value={number(item.performance, 0)}
                    />
                    <Metric
                        label="Subscribers"
                        value={number(item.subscriber_count, 0)}
                    />
                </dl>

                <p className="text-xs text-muted-foreground">
                    Observed:{' '}
                    {item.observed_at
                        ? new Intl.DateTimeFormat(undefined, {
                              dateStyle: 'medium',
                              timeStyle: 'short',
                              timeZone: timezone,
                          }).format(new Date(item.observed_at))
                        : 'Unavailable'}
                </p>

                <div className="flex flex-wrap gap-2 border-t pt-4">
                    <FavoriteToggle
                        library={library}
                        targetType={
                            item.entity_type === 'candidate'
                                ? 'niche_candidate'
                                : item.entity_type
                        }
                        targetReference={item.id}
                        label={item.title}
                    />
                    {item.analyzer_url && (
                        <Button asChild size="sm">
                            <Link href={item.analyzer_url}>
                                <FlaskConical className="size-4" />
                                {item.sources.includes('analyzer')
                                    ? 'Open Analyzer'
                                    : 'Analyze (explicit)'}
                            </Link>
                        </Button>
                    )}
                    {item.entity_type === 'candidate' &&
                        item.validate_url &&
                        (item.validate_url.startsWith('/research/') ? (
                            <Button asChild size="sm">
                                <Link href={item.validate_url}>
                                    Open validation
                                </Link>
                            </Button>
                        ) : (
                            <Button type="button" size="sm" onClick={validate}>
                                Validate (uses quota)
                            </Button>
                        ))}
                    {item.youtube_url && (
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={item.youtube_url}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                YouTube <ArrowUpRight className="size-4" />
                            </a>
                        </Button>
                    )}
                    {item.entity_type !== 'candidate' &&
                        capabilities.watchlist_available &&
                        (item.watchlist_public_id ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href="/watchlist">Watched</Link>
                            </Button>
                        ) : (
                            <Form
                                action="/watchlist"
                                method="post"
                                disableWhileProcessing
                            >
                                <input
                                    type="hidden"
                                    name="target_type"
                                    value={item.entity_type}
                                />
                                <input
                                    type="hidden"
                                    name="target_reference"
                                    value={item.id}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                >
                                    Watch
                                </Button>
                            </Form>
                        ))}
                    {capabilities.workspace_available && (
                        <WorkspaceHandoff
                            workspaces={workspaces}
                            targetType={
                                item.entity_type === 'candidate'
                                    ? 'niche_candidate'
                                    : item.entity_type
                            }
                            targetReference={item.id}
                        />
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="space-y-1.5 text-sm font-medium">
            <span>{label}</span>
            {children}
        </label>
    );
}

function Select({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {options.map(([optionValue, label]) => (
                <option key={optionValue} value={optionValue}>
                    {label}
                </option>
            ))}
        </select>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium tabular-nums">{value}</dd>
        </div>
    );
}

function number(value: number | null, digits: number): string {
    return value === null
        ? 'Unavailable'
        : new Intl.NumberFormat(undefined, {
              maximumFractionDigits: digits,
          }).format(value);
}

function numeric(value: string): number | null {
    return value === '' ? null : Number(value);
}

function compactQuery(filters: ExploreFilters, page: number) {
    return Object.fromEntries(
        Object.entries({ ...filters, page }).filter(
            ([, value]) => value !== null && value !== '',
        ),
    );
}

function defaultFilters(entityType: ExploreEntityType): ExploreFilters {
    return {
        entity_type: entityType,
        source: 'all',
        market: null,
        category: null,
        topic: null,
        breakout: null,
        channel_size: null,
        min_performance: null,
        min_score: null,
        min_confidence: null,
        observed_from: null,
        observed_to: null,
        organization: 'all',
        sort: 'latest',
    };
}

ExploreIndex.layout = {
    breadcrumbs: [{ title: 'Explore', href: '/explore' }],
};
