import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Clock3,
    Globe2,
    ListFilter,
    Save,
    SlidersHorizontal,
} from 'lucide-react';
import PreferencesController from '@/actions/App/Http/Controllers/Settings/PreferencesController';
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
import type { Auth } from '@/types';

type MarketOption = {
    value: string;
    label: string;
};

type PageProps = {
    auth: Auth;
    preferenceOptions: {
        markets: MarketOption[];
        timezones: string[];
        resultDepths: number[];
    };
};

type PreferenceData = {
    timezone: string;
    default_market_key: string;
    default_result_depth: number;
};

function previewTime(timezone: string): string {
    try {
        return new Intl.DateTimeFormat('en', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timezone,
        }).format(new Date('2026-08-21T12:34:00Z'));
    } catch {
        return 'Enter a valid timezone to preview timestamps.';
    }
}

export default function Preferences({ preferenceOptions }: PageProps) {
    const { auth } = usePage<PageProps>().props;
    const form = useForm<PreferenceData>({
        timezone: auth.user.timezone,
        default_market_key: auth.user.default_market_key ?? '',
        default_result_depth: auth.user.default_result_depth,
    });
    const selectedMarket = preferenceOptions.markets.find(
        (market) => market.value === form.data.default_market_key,
    );

    return (
        <>
            <Head title="Research preferences" />

            <h1 className="sr-only">Research preferences</h1>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-info/10 p-2 text-info-foreground">
                            <SlidersHorizontal
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Research preferences</CardTitle>
                            <CardDescription>
                                Choose the defaults for new research and how
                                stored UTC timestamps appear in your workspace.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    {preferenceOptions.markets.length === 0 && (
                        <Alert>
                            <Globe2 aria-hidden="true" />
                            <AlertTitle>
                                No enabled markets available
                            </AlertTitle>
                            <AlertDescription>
                                You can still save timezone and result-depth
                                preferences. Ask for a market each time until a
                                local market is enabled.
                            </AlertDescription>
                        </Alert>
                    )}

                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(PreferencesController.update.url(), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="timezone">
                                    <Clock3
                                        className="mr-1.5 inline size-3.5"
                                        aria-hidden="true"
                                    />
                                    Timezone
                                </Label>
                                <Input
                                    id="timezone"
                                    list="timezone-options"
                                    value={form.data.timezone}
                                    required
                                    autoComplete="off"
                                    placeholder="Europe/Chisinau"
                                    aria-describedby="timezone-help"
                                    aria-invalid={Boolean(form.errors.timezone)}
                                    onChange={(event) =>
                                        form.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <datalist id="timezone-options">
                                    {preferenceOptions.timezones.map(
                                        (timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            />
                                        ),
                                    )}
                                </datalist>
                                <p
                                    id="timezone-help"
                                    className="text-xs leading-5 text-muted-foreground"
                                >
                                    Stored timestamps stay in UTC and display in
                                    this timezone.
                                </p>
                                <InputError message={form.errors.timezone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="default_market_key">
                                    <Globe2
                                        className="mr-1.5 inline size-3.5"
                                        aria-hidden="true"
                                    />
                                    Default market
                                </Label>
                                <select
                                    id="default_market_key"
                                    value={form.data.default_market_key}
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    aria-describedby="market-help"
                                    aria-invalid={Boolean(
                                        form.errors.default_market_key,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_market_key',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Ask me each time</option>
                                    {preferenceOptions.markets.map((market) => (
                                        <option
                                            key={market.value}
                                            value={market.value}
                                        >
                                            {market.label}
                                        </option>
                                    ))}
                                </select>
                                <p
                                    id="market-help"
                                    className="text-xs leading-5 text-muted-foreground"
                                >
                                    You can still choose another market for an
                                    individual run.
                                </p>
                                <InputError
                                    message={form.errors.default_market_key}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="default_result_depth">
                                    <ListFilter
                                        className="mr-1.5 inline size-3.5"
                                        aria-hidden="true"
                                    />
                                    Default result depth
                                </Label>
                                <select
                                    id="default_result_depth"
                                    value={form.data.default_result_depth}
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    aria-describedby="depth-help"
                                    aria-invalid={Boolean(
                                        form.errors.default_result_depth,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_result_depth',
                                            Number(event.target.value),
                                        )
                                    }
                                >
                                    {preferenceOptions.resultDepths.map(
                                        (depth) => (
                                            <option key={depth} value={depth}>
                                                {depth} videos
                                            </option>
                                        ),
                                    )}
                                </select>
                                <p
                                    id="depth-help"
                                    className="text-xs leading-5 text-muted-foreground"
                                >
                                    Higher depth uses more search calls and
                                    takes longer to enrich.
                                </p>
                                <InputError
                                    message={form.errors.default_result_depth}
                                />
                            </div>
                        </div>

                        <Card className="border-dashed bg-muted/20">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Formatting preview
                                </CardTitle>
                                <CardDescription>
                                    A display-only sample. Changing these
                                    preferences never rewrites saved evidence.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Stored UTC time
                                    </p>
                                    <p className="mt-1 font-medium">
                                        {previewTime(form.data.timezone)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        New research market
                                    </p>
                                    <p className="mt-1 font-medium">
                                        {selectedMarket?.label ??
                                            'Ask me each time'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Result depth
                                    </p>
                                    <p className="mt-1 font-medium">
                                        {new Intl.NumberFormat('en').format(
                                            form.data.default_result_depth,
                                        )}{' '}
                                        videos
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                            <Button
                                disabled={form.processing}
                                data-test="update-preferences-button"
                            >
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <Save aria-hidden="true" />
                                )}
                                {form.processing
                                    ? 'Saving preferences…'
                                    : 'Save preferences'}
                            </Button>
                            {form.recentlySuccessful && (
                                <span
                                    className="text-sm text-success-foreground"
                                    role="status"
                                >
                                    Preferences saved.
                                </span>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}

Preferences.layout = {
    breadcrumbs: [
        {
            title: 'Research preferences',
            href: '/settings/preferences',
        },
    ],
};
