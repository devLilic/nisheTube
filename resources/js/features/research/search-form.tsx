import { useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type {
    ResearchPreflight,
    ResearchRepeatSource,
    ValidationPreset,
} from '@/pages/research/create';
import type { MarketKey, QuotaSummary, ResearchMarketOption } from '@/types';

type FormData = {
    submission_token: string;
    workflow_mode: 'validate_idea';
    preset_key: string;
    query_text: string;
    market_key: MarketKey;
    language: 'en' | 'ro' | 'ru';
    requested_result_count: number;
    published_window: string;
    published_after: string;
    published_before: string;
    search_order: string;
    video_duration: string;
    video_category_id: string;
    content_format: string;
    target_channel_size: string;
};

const languageNames = { en: 'English', ro: 'Romanian', ru: 'Russian' } as const;
const windowNames: Record<string, string> = {
    any: 'Any time',
    past_week: 'Past 7 days',
    past_month: 'Past 30 days',
    past_three_months: 'Past 90 days',
    past_year: 'Past year',
    custom: 'Custom dates',
};

export function SearchForm({
    markets,
    defaults,
    submissionToken,
    validationPresets,
    preflight,
    repeatSource,
}: {
    markets: ResearchMarketOption[];
    defaults: { market_key: MarketKey; result_depth: 25 | 50 | 100 | 200 };
    submissionToken: string;
    validationPresets: ValidationPreset[];
    preflight: ResearchPreflight;
    repeatSource: ResearchRepeatSource | null;
}) {
    const defaultMarket =
        markets.find((market) => market.key === defaults.market_key) ??
        markets[0]!;
    const balanced =
        validationPresets.find((preset) => preset.key === 'balanced') ??
        validationPresets[0];
    const form = useForm<FormData>({
        submission_token: submissionToken,
        workflow_mode: 'validate_idea',
        preset_key: repeatSource ? 'custom' : (balanced?.key ?? 'custom'),
        query_text: repeatSource?.query_text ?? '',
        market_key: repeatSource?.market_key ?? defaultMarket.key,
        language: (markets.find(
            (market) => market.key === repeatSource?.market_key,
        )?.relevance_language ??
            defaultMarket.relevance_language) as FormData['language'],
        requested_result_count:
            repeatSource?.requested_result_count ??
            balanced?.requested_result_count ??
            defaults.result_depth,
        published_window:
            repeatSource?.published_window ??
            balanced?.published_window ??
            'past_three_months',
        published_after: repeatSource?.published_after ?? '',
        published_before: repeatSource?.published_before ?? '',
        search_order:
            repeatSource?.search_order ?? balanced?.search_order ?? 'relevance',
        video_duration:
            repeatSource?.video_duration ?? balanced?.video_duration ?? 'any',
        video_category_id: repeatSource?.video_category_id ?? '',
        content_format:
            repeatSource?.content_format ?? balanced?.content_format ?? 'any',
        target_channel_size:
            repeatSource?.target_channel_size ??
            balanced?.target_channel_size ??
            'any',
    });
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const { youtubeQuota } = usePage<{ youtubeQuota: QuotaSummary | null }>()
        .props;
    const searchRequests = Math.ceil(
        form.data.requested_result_count / preflight.max_results_per_request,
    );
    const estimatedCost = searchRequests * preflight.search_request_cost;
    const searchBucket = useMemo(
        () =>
            youtubeQuota?.buckets.find(
                (bucket) =>
                    bucket.bucket === 'search' ||
                    bucket.bucket === 'search.list',
            ),
        [youtubeQuota],
    );
    const exceedsEstimate =
        searchBucket !== undefined && estimatedCost > searchBucket.remaining;

    function selectPreset(preset: ValidationPreset) {
        form.setData((data) => ({
            ...data,
            preset_key: preset.key,
            requested_result_count: preset.requested_result_count,
            published_window: preset.published_window,
            search_order: preset.search_order,
            video_duration: preset.video_duration,
            content_format: preset.content_format,
            target_channel_size: preset.target_channel_size,
            published_after: '',
            published_before: '',
        }));
    }

    function setCustom<K extends keyof FormData>(key: K, value: FormData[K]) {
        form.setData((data) => ({
            ...data,
            [key]: value,
            preset_key: 'custom',
        }));
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!form.processing && !exceedsEstimate) {
            form.post('/search', { preserveScroll: true });
        }
    }

    const selectedMarket =
        markets.find((market) => market.key === form.data.market_key) ??
        defaultMarket;

    return (
        <form
            onSubmit={submit}
            className="space-y-6"
            aria-busy={form.processing}
        >
            <Card className="overflow-hidden border-primary/15">
                <CardHeader className="border-b bg-gradient-to-r from-primary/8 via-transparent to-info/8">
                    <CardTitle>Validate my idea</CardTitle>
                    <CardDescription>
                        Test one specific niche against an observed sample of
                        public YouTube results. This is not YouTube search
                        volume.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-7 pt-1">
                    <div className="grid gap-2">
                        <Label htmlFor="query_text" className="text-base">
                            Niche, topic, or audience problem
                        </Label>
                        <Input
                            id="query_text"
                            value={form.data.query_text}
                            onChange={(event) =>
                                form.setData('query_text', event.target.value)
                            }
                            placeholder="e.g. small apartment woodworking"
                            autoFocus
                            maxLength={500}
                            aria-invalid={Boolean(form.errors.query_text)}
                            className="h-12 text-base md:text-base"
                        />
                        <InputError message={form.errors.query_text} />
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Market</legend>
                        <div className="grid gap-3 lg:grid-cols-3">
                            {markets.map((market) => (
                                <label
                                    key={market.key}
                                    className="cursor-pointer rounded-xl border bg-card p-4 has-checked:border-primary has-checked:bg-primary/6 has-checked:ring-2 has-checked:ring-primary/15"
                                >
                                    <input
                                        type="radio"
                                        className="sr-only"
                                        checked={
                                            form.data.market_key === market.key
                                        }
                                        onChange={() =>
                                            form.setData((data) => ({
                                                ...data,
                                                market_key: market.key,
                                                language:
                                                    market.relevance_language as FormData['language'],
                                            }))
                                        }
                                    />
                                    <span className="block text-sm font-semibold">
                                        {market.name}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        {
                                            languageNames[
                                                market.relevance_language as FormData['language']
                                            ]
                                        }
                                        {market.region_code
                                            ? ` relevance in ${market.region_code}`
                                            : ' global relevance sample'}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.market_key} />
                        <InputError message={form.errors.language} />
                    </fieldset>

                    <div className="space-y-3">
                        <div>
                            <h3 className="text-sm font-medium">
                                Validation preset
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Choose a starting point, then refine exact
                                inputs below.
                            </p>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            {validationPresets.map((preset) => (
                                <Button
                                    key={preset.key}
                                    type="button"
                                    variant={
                                        form.data.preset_key === preset.key
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="h-auto min-h-11 justify-start text-left whitespace-normal"
                                    onClick={() => selectPreset(preset)}
                                >
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="published_window">
                                <CalendarDays className="mr-1.5 inline size-3.5" />
                                Period
                            </Label>
                            <select
                                id="published_window"
                                value={form.data.published_window}
                                onChange={(event) =>
                                    setCustom(
                                        'published_window',
                                        event.target.value,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {Object.entries(windowNames).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError
                                message={form.errors.published_window}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="requested_result_count">
                                Result depth
                            </Label>
                            <select
                                id="requested_result_count"
                                value={form.data.requested_result_count}
                                onChange={(event) =>
                                    setCustom(
                                        'requested_result_count',
                                        Number(event.target.value),
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {[25, 50, 100, 200].map((depth) => (
                                    <option key={depth} value={depth}>
                                        Up to {depth} videos
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.requested_result_count}
                            />
                        </div>
                    </div>

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
                                <SlidersHorizontal />
                                Advanced filters
                                <ChevronDown
                                    className={cn(
                                        'transition-transform',
                                        advancedOpen && 'rotate-180',
                                    )}
                                />
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-5 pt-3">
                            {form.data.published_window === 'custom' && (
                                <div className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="published_after">
                                            Published from
                                        </Label>
                                        <Input
                                            id="published_after"
                                            type="date"
                                            value={form.data.published_after}
                                            onChange={(event) =>
                                                form.setData(
                                                    'published_after',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors.published_after
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="published_before">
                                            Published through
                                        </Label>
                                        <Input
                                            id="published_before"
                                            type="date"
                                            value={form.data.published_before}
                                            onChange={(event) =>
                                                form.setData(
                                                    'published_before',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors.published_before
                                            }
                                        />
                                    </div>
                                </div>
                            )}
                            <div className="grid gap-5 rounded-xl border bg-muted/20 p-4 md:grid-cols-2 xl:grid-cols-3">
                                <SelectField
                                    label="Order"
                                    value={form.data.search_order}
                                    onChange={(value) =>
                                        setCustom('search_order', value)
                                    }
                                    options={[
                                        ['relevance', 'Relevance'],
                                        ['date', 'Newest first'],
                                        ['viewCount', 'View count'],
                                        ['rating', 'Rating'],
                                        ['title', 'Title'],
                                    ]}
                                />
                                <SelectField
                                    label="Duration"
                                    value={form.data.video_duration}
                                    onChange={(value) =>
                                        setCustom('video_duration', value)
                                    }
                                    options={[
                                        ['any', 'Any duration'],
                                        ['short', 'Under 4 minutes'],
                                        ['medium', '4–20 minutes'],
                                        ['long', 'Over 20 minutes'],
                                    ]}
                                />
                                <SelectField
                                    label="Content format"
                                    value={form.data.content_format}
                                    onChange={(value) =>
                                        setCustom('content_format', value)
                                    }
                                    options={[
                                        ['any', 'Any format'],
                                        ['mixed', 'Mixed formats'],
                                        ['long_form', 'Long-form'],
                                        ['shorts', 'Shorts opportunity'],
                                    ]}
                                />
                                <SelectField
                                    label="Target channel size"
                                    value={form.data.target_channel_size}
                                    onChange={(value) =>
                                        setCustom('target_channel_size', value)
                                    }
                                    options={[
                                        ['any', 'Any size'],
                                        ['small', 'Small · under 100K'],
                                        ['mid_size', 'Mid-size · 100K–999K'],
                                        ['large', 'Large · 1M+'],
                                    ]}
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="video_category_id">
                                        YouTube category ID
                                    </Label>
                                    <Input
                                        id="video_category_id"
                                        value={form.data.video_category_id}
                                        onChange={(event) =>
                                            setCustom(
                                                'video_category_id',
                                                event.target.value,
                                            )
                                        }
                                        inputMode="numeric"
                                        placeholder="Optional, e.g. 26"
                                    />
                                    <InputError
                                        message={form.errors.video_category_id}
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <div
                        className="rounded-xl border bg-muted/20 p-4"
                        aria-label="Exact validation preflight"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="font-medium">Exact preflight</h3>
                            <Badge variant="outline">Frozen on creation</Badge>
                        </div>
                        <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <Preflight
                                label="Term"
                                value={
                                    form.data.query_text.trim() || 'Not entered'
                                }
                            />
                            <Preflight
                                label="Market / language"
                                value={`${selectedMarket.name} / ${languageNames[form.data.language]}`}
                            />
                            <Preflight
                                label="Period"
                                value={
                                    form.data.published_window === 'custom'
                                        ? `${form.data.published_after || 'start missing'} → ${form.data.published_before || 'end missing'}`
                                        : windowNames[
                                              form.data.published_window
                                          ]
                                }
                            />
                            <Preflight
                                label="Depth / order"
                                value={`${form.data.requested_result_count} / ${form.data.search_order}`}
                            />
                            <Preflight
                                label="Format / duration"
                                value={`${form.data.content_format} / ${form.data.video_duration}`}
                            />
                            <Preflight
                                label="Channel-size lens"
                                value={`${form.data.target_channel_size} (analysis context, not an API filter)`}
                            />
                            <Preflight
                                label="Category"
                                value={form.data.video_category_id || 'Any'}
                            />
                            <Preflight
                                label="Estimated Search cost"
                                value={`${searchRequests} request${searchRequests === 1 ? '' : 's'} × ${preflight.search_request_cost} = ${estimatedCost} ${estimatedCost === 1 && preflight.search_request_measure === 'requests' ? 'request' : preflight.search_request_measure}`}
                            />
                        </dl>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <Alert
                    className={cn(
                        exceedsEstimate
                            ? 'border-warning/45 bg-warning/10'
                            : 'border-info/35 bg-info/8',
                    )}
                >
                    <CircleDollarSign />
                    <AlertTitle>NisheTube quota estimate</AlertTitle>
                    <AlertDescription>
                        {searchBucket
                            ? `${searchBucket.remaining} of ${searchBucket.allowance} ${searchBucket.measure} remain in the local estimate.`
                            : 'Quota status is unavailable; Google Cloud Console remains authoritative.'}
                        {exceedsEstimate &&
                            ' Reduce the depth or wait for the Pacific Time reset.'}
                    </AlertDescription>
                </Alert>
                <Button
                    type="submit"
                    size="lg"
                    disabled={
                        form.processing ||
                        exceedsEstimate ||
                        form.data.query_text.trim() === ''
                    }
                >
                    {form.processing ? <Spinner /> : <Search />}
                    {form.processing ? 'Creating run' : 'Start validation'}
                </Button>
            </div>
        </form>
    );
}

function SelectField({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Preflight({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b py-1.5 last:border-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium break-words">{value}</dd>
        </div>
    );
}
