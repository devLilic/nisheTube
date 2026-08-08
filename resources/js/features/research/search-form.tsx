import { Form, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    Globe2,
    Languages,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ResearchController from '@/actions/App/Http/Controllers/Research/ResearchController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { MarketKey, QuotaSummary, ResearchMarketOption } from '@/types';

type SearchFormProps = {
    markets: ResearchMarketOption[];
    defaults: {
        market_key: MarketKey;
        result_depth: 25 | 50 | 100 | 200;
    };
};

const marketNotes: Record<MarketKey, string> = {
    global_en: 'English-oriented global sample',
    ro_ro: 'Romanian relevance in Romania',
    ru_ru: 'Russian relevance in Russia',
};

export function SearchForm({ markets, defaults }: SearchFormProps) {
    const { youtubeQuota } = usePage<{ youtubeQuota: QuotaSummary | null }>()
        .props;
    const [depth, setDepth] = useState<number>(defaults.result_depth);
    const [publishedWindow, setPublishedWindow] = useState('past_month');
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const estimatedCalls = Math.ceil(depth / 50);
    const searchBucket = useMemo(
        () =>
            youtubeQuota?.buckets.find(
                (bucket) => bucket.bucket === 'search',
            ) ??
            youtubeQuota?.buckets.find(
                (bucket) => bucket.bucket === 'search.list',
            ),
        [youtubeQuota],
    );
    const exceedsEstimate =
        searchBucket !== undefined && estimatedCalls > searchBucket.remaining;

    return (
        <Form
            {...ResearchController.store.form()}
            disableWhileProcessing
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <Card className="overflow-hidden border-primary/15">
                        <CardHeader className="border-b bg-gradient-to-r from-primary/8 via-transparent to-info/8">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-primary p-2.5 text-primary-foreground shadow-sm">
                                    <Search
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <CardTitle>
                                        Research a YouTube niche
                                    </CardTitle>
                                    <CardDescription className="mt-1 leading-6">
                                        Create a durable run from an observed
                                        sample of public videos.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-7 pt-1">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="query_text"
                                    className="text-base"
                                >
                                    What niche do you want to investigate?
                                </Label>
                                <Input
                                    id="query_text"
                                    name="query_text"
                                    placeholder="e.g. small apartment woodworking"
                                    autoFocus
                                    required
                                    maxLength={500}
                                    aria-invalid={Boolean(errors.query_text)}
                                    className="h-12 text-base md:text-base"
                                />
                                <p className="text-xs leading-5 text-muted-foreground">
                                    Use a specific topic, audience, problem, or
                                    format. NisheTube measures observed demand,
                                    not exact search volume.
                                </p>
                                <InputError message={errors.query_text} />
                            </div>

                            <fieldset className="space-y-3">
                                <legend className="text-sm font-medium">
                                    Market
                                </legend>
                                <div className="grid gap-3 lg:grid-cols-3">
                                    {markets.map((market) => {
                                        const Icon =
                                            market.key === 'global_en'
                                                ? Globe2
                                                : Languages;

                                        return (
                                            <label
                                                key={market.key}
                                                className="group relative cursor-pointer"
                                            >
                                                <input
                                                    type="radio"
                                                    name="market_key"
                                                    value={market.key}
                                                    defaultChecked={
                                                        market.key ===
                                                        defaults.market_key
                                                    }
                                                    className="peer sr-only"
                                                />
                                                <span className="flex min-h-24 items-start gap-3 rounded-xl border bg-card p-4 shadow-xs transition group-hover:border-primary/45 peer-checked:border-primary peer-checked:bg-primary/6 peer-checked:ring-2 peer-checked:ring-primary/15">
                                                    <span className="rounded-lg bg-muted p-2 text-muted-foreground peer-checked:text-primary">
                                                        <Icon
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </span>
                                                    <span>
                                                        <span className="block text-sm font-semibold">
                                                            {market.name}
                                                        </span>
                                                        <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                                            {
                                                                marketNotes[
                                                                    market.key
                                                                ]
                                                            }
                                                        </span>
                                                    </span>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.market_key} />
                            </fieldset>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="published_window">
                                        <CalendarDays
                                            className="mr-1.5 inline size-3.5"
                                            aria-hidden="true"
                                        />
                                        Published window
                                    </Label>
                                    <select
                                        id="published_window"
                                        name="published_window"
                                        value={publishedWindow}
                                        onChange={(event) =>
                                            setPublishedWindow(
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        aria-invalid={Boolean(
                                            errors.published_window,
                                        )}
                                    >
                                        <option value="any">Any time</option>
                                        <option value="past_week">
                                            Past 7 days
                                        </option>
                                        <option value="past_month">
                                            Past 30 days
                                        </option>
                                        <option value="past_three_months">
                                            Past 90 days
                                        </option>
                                        <option value="past_year">
                                            Past year
                                        </option>
                                        <option value="custom">
                                            Custom dates
                                        </option>
                                    </select>
                                    <InputError
                                        message={errors.published_window}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="requested_result_count">
                                        Result depth
                                    </Label>
                                    <select
                                        id="requested_result_count"
                                        name="requested_result_count"
                                        value={depth}
                                        onChange={(event) =>
                                            setDepth(Number(event.target.value))
                                        }
                                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        aria-invalid={Boolean(
                                            errors.requested_result_count,
                                        )}
                                    >
                                        {[25, 50, 100, 200].map((option) => (
                                            <option key={option} value={option}>
                                                Up to {option} videos
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.requested_result_count}
                                    />
                                </div>
                            </div>

                            {publishedWindow === 'custom' && (
                                <div className="grid gap-5 rounded-xl border bg-muted/25 p-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="published_after">
                                            Published from
                                        </Label>
                                        <Input
                                            id="published_after"
                                            name="published_after"
                                            type="date"
                                            required
                                            aria-invalid={Boolean(
                                                errors.published_after,
                                            )}
                                        />
                                        <InputError
                                            message={errors.published_after}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="published_before">
                                            Published through
                                        </Label>
                                        <Input
                                            id="published_before"
                                            name="published_before"
                                            type="date"
                                            required
                                            aria-invalid={Boolean(
                                                errors.published_before,
                                            )}
                                        />
                                        <InputError
                                            message={errors.published_before}
                                        />
                                    </div>
                                </div>
                            )}

                            <Collapsible
                                open={advancedOpen}
                                onOpenChange={setAdvancedOpen}
                            >
                                <CollapsibleTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="-ml-3"
                                    >
                                        <SlidersHorizontal aria-hidden="true" />
                                        Advanced filters
                                        <ChevronDown
                                            className={cn(
                                                'transition-transform',
                                                advancedOpen && 'rotate-180',
                                            )}
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="pt-3">
                                    <div className="grid gap-5 rounded-xl border bg-muted/20 p-4 md:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="search_order">
                                                Result order
                                            </Label>
                                            <select
                                                id="search_order"
                                                name="search_order"
                                                defaultValue="relevance"
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="relevance">
                                                    Relevance
                                                </option>
                                                <option value="date">
                                                    Newest first
                                                </option>
                                                <option value="viewCount">
                                                    View count
                                                </option>
                                                <option value="rating">
                                                    Rating
                                                </option>
                                                <option value="title">
                                                    Title
                                                </option>
                                            </select>
                                            <InputError
                                                message={errors.search_order}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="video_duration">
                                                Video duration
                                            </Label>
                                            <select
                                                id="video_duration"
                                                name="video_duration"
                                                defaultValue="any"
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="any">
                                                    Any duration
                                                </option>
                                                <option value="short">
                                                    Under 4 minutes
                                                </option>
                                                <option value="medium">
                                                    4-20 minutes
                                                </option>
                                                <option value="long">
                                                    Over 20 minutes
                                                </option>
                                            </select>
                                            <InputError
                                                message={errors.video_duration}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="video_category_id">
                                                YouTube category ID
                                            </Label>
                                            <Input
                                                id="video_category_id"
                                                name="video_category_id"
                                                inputMode="numeric"
                                                pattern="[0-9]*"
                                                placeholder="Optional, e.g. 26"
                                                aria-invalid={Boolean(
                                                    errors.video_category_id,
                                                )}
                                            />
                                            <InputError
                                                message={
                                                    errors.video_category_id
                                                }
                                            />
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                        <Alert
                            className={cn(
                                exceedsEstimate
                                    ? 'border-warning/45 bg-warning/10 text-warning-foreground'
                                    : 'border-info/35 bg-info/8 text-info-foreground',
                            )}
                        >
                            <CircleDollarSign aria-hidden="true" />
                            <AlertTitle>
                                Up to {estimatedCalls} search{' '}
                                {estimatedCalls === 1 ? 'call' : 'calls'}
                            </AlertTitle>
                            <AlertDescription className="text-current/80">
                                {searchBucket
                                    ? `${searchBucket.remaining} of ${searchBucket.allowance} locally estimated calls remain today.`
                                    : 'Quota is estimated locally and Google Cloud Console remains authoritative.'}
                                {exceedsEstimate &&
                                    ' Reduce the depth or wait for the Pacific Time reset.'}
                            </AlertDescription>
                        </Alert>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || exceedsEstimate}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Search aria-hidden="true" />
                            )}
                            {processing ? 'Creating run...' : 'Start research'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
