import { Link, useForm, usePage } from '@inertiajs/react';
import { CircleDollarSign, Compass, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    DiscoverySampleRun,
    MarketKey,
    QuotaSummary,
    ResearchMarketOption,
} from '@/types';

type SeedInput = { query: string; research_run_id: string };

type DiscoveryFormData = {
    market_key: MarketKey;
    sample_per_seed: 10 | 25 | 50;
    candidate_limit: 10 | 20;
    seeds: SeedInput[];
};

export function DiscoveryForm({
    markets,
    defaultMarketKey,
    sampleRuns,
}: {
    markets: ResearchMarketOption[];
    defaultMarketKey: MarketKey;
    sampleRuns: DiscoverySampleRun[];
}) {
    const { youtubeQuota } = usePage<{ youtubeQuota: QuotaSummary | null }>()
        .props;
    const initialSample = sampleRuns.find(
        (sample) => sample.market_key === defaultMarketKey,
    );
    const form = useForm<DiscoveryFormData>({
        market_key: defaultMarketKey,
        sample_per_seed: 25,
        candidate_limit: 20,
        seeds: initialSample
            ? [
                  {
                      query: initialSample.query_text,
                      research_run_id: initialSample.public_id,
                  },
              ]
            : [{ query: '', research_run_id: '' }],
    });
    const [selectedMarket, setSelectedMarket] = useState(defaultMarketKey);
    const availableSamples = useMemo(
        () =>
            sampleRuns.filter((sample) => sample.market_key === selectedMarket),
        [sampleRuns, selectedMarket],
    );
    const searchBucket = youtubeQuota?.buckets.find(
        (bucket) =>
            bucket.bucket === 'search' || bucket.bucket === 'search.list',
    );

    function changeMarket(marketKey: MarketKey) {
        const firstSample = sampleRuns.find(
            (sample) => sample.market_key === marketKey,
        );
        setSelectedMarket(marketKey);
        form.setData({
            ...form.data,
            market_key: marketKey,
            seeds: firstSample
                ? [
                      {
                          query: firstSample.query_text,
                          research_run_id: firstSample.public_id,
                      },
                  ]
                : [{ query: '', research_run_id: '' }],
        });
    }

    function updateSeed(index: number, patch: Partial<SeedInput>) {
        form.setData(
            'seeds',
            form.data.seeds.map((seed, seedIndex) =>
                seedIndex === index ? { ...seed, ...patch } : seed,
            ),
        );
    }

    function chooseSample(index: number, publicId: string) {
        const sample = availableSamples.find(
            (candidate) => candidate.public_id === publicId,
        );
        updateSeed(index, {
            research_run_id: publicId,
            query: sample?.query_text ?? form.data.seeds[index].query,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/discover');
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card className="overflow-hidden border-primary/15">
                <CardHeader className="border-b bg-gradient-to-r from-primary/8 via-transparent to-info/8">
                    <CardTitle>Explore stored research samples</CardTitle>
                    <CardDescription>
                        Select completed samples, refine their seed phrases, and
                        detect recurring breakout themes without another API
                        call.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-7 pt-1">
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Market</legend>
                        <div className="grid gap-3 md:grid-cols-3">
                            {markets.map((market) => (
                                <label
                                    key={market.key}
                                    className="cursor-pointer rounded-xl border bg-card p-4 has-checked:border-primary has-checked:bg-primary/6 has-checked:ring-2 has-checked:ring-primary/15"
                                >
                                    <input
                                        type="radio"
                                        className="sr-only"
                                        checked={selectedMarket === market.key}
                                        onChange={() =>
                                            changeMarket(market.key)
                                        }
                                    />
                                    <span className="block text-sm font-semibold">
                                        {market.name}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        {
                                            sampleRuns.filter(
                                                (sample) =>
                                                    sample.market_key ===
                                                    market.key,
                                            ).length
                                        }{' '}
                                        stored samples
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.market_key} />
                    </fieldset>

                    {availableSamples.length === 0 ? (
                        <Alert className="border-warning/40 bg-warning/10">
                            <Compass aria-hidden="true" />
                            <AlertTitle>
                                No completed samples in this market
                            </AlertTitle>
                            <AlertDescription>
                                Complete a research run first, then return here
                                to use its immutable video snapshot as discovery
                                evidence.
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                >
                                    <Link href="/search">Start a search</Link>
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Seed samples
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Each seed must use a different completed
                                        run.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        form.data.seeds.length >= 10 ||
                                        form.data.seeds.length >=
                                            availableSamples.length
                                    }
                                    onClick={() =>
                                        form.setData('seeds', [
                                            ...form.data.seeds,
                                            { query: '', research_run_id: '' },
                                        ])
                                    }
                                >
                                    <Plus aria-hidden="true" /> Add seed
                                </Button>
                            </div>
                            {form.data.seeds.map((seed, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-xl border bg-muted/15 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor={`seed-${index}`}>
                                            Seed phrase {index + 1}
                                        </Label>
                                        <Input
                                            id={`seed-${index}`}
                                            value={seed.query}
                                            maxLength={500}
                                            onChange={(event) =>
                                                updateSeed(index, {
                                                    query: event.target.value,
                                                })
                                            }
                                            placeholder="e.g. compact apartment storage"
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `seeds.${index}.query`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`sample-${index}`}>
                                            Evidence sample
                                        </Label>
                                        <select
                                            id={`sample-${index}`}
                                            value={seed.research_run_id}
                                            onChange={(event) =>
                                                chooseSample(
                                                    index,
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                Select a completed run
                                            </option>
                                            {availableSamples.map((sample) => (
                                                <option
                                                    key={sample.public_id}
                                                    value={sample.public_id}
                                                    disabled={form.data.seeds.some(
                                                        (item, itemIndex) =>
                                                            itemIndex !==
                                                                index &&
                                                            item.research_run_id ===
                                                                sample.public_id,
                                                    )}
                                                >
                                                    {sample.query_text} ·{' '}
                                                    {sample.video_count} videos
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                form.errors[
                                                    `seeds.${index}.research_run_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove seed ${index + 1}`}
                                        disabled={form.data.seeds.length === 1}
                                        onClick={() =>
                                            form.setData(
                                                'seeds',
                                                form.data.seeds.filter(
                                                    (_, seedIndex) =>
                                                        seedIndex !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="sample_per_seed">
                                Evidence depth per seed
                            </Label>
                            <select
                                id="sample_per_seed"
                                value={form.data.sample_per_seed}
                                onChange={(event) =>
                                    form.setData(
                                        'sample_per_seed',
                                        Number(event.target.value) as
                                            10 | 25 | 50,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value={10}>
                                    10 videos · fast scan
                                </option>
                                <option value={25}>25 videos · balanced</option>
                                <option value={50}>
                                    50 videos · broad scan
                                </option>
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="candidate_limit">
                                Candidate budget
                            </Label>
                            <select
                                id="candidate_limit"
                                value={form.data.candidate_limit}
                                onChange={(event) =>
                                    form.setData(
                                        'candidate_limit',
                                        Number(event.target.value) as 10 | 20,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value={10}>
                                    Up to 10 focused candidates
                                </option>
                                <option value={20}>
                                    Up to 20 broad candidates
                                </option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <Alert className="border-info/35 bg-info/8">
                    <CircleDollarSign aria-hidden="true" />
                    <AlertTitle>No discovery collection calls</AlertTitle>
                    <AlertDescription>
                        This analysis reuses stored snapshots. Candidate
                        validation later starts a normal search;{' '}
                        {searchBucket?.remaining ?? 'unknown'} locally estimated
                        search calls remain today.
                    </AlertDescription>
                </Alert>
                <Button
                    type="submit"
                    size="lg"
                    disabled={form.processing || availableSamples.length === 0}
                >
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <Compass aria-hidden="true" />
                    )}
                    {form.processing
                        ? 'Queuing discovery...'
                        : 'Start discovery'}
                </Button>
            </div>
        </form>
    );
}
