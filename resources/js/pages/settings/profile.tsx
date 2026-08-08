import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Clock3, Globe2, ListFilter, Save, UserRound } from 'lucide-react';
import PreferencesController from '@/actions/App/Http/Controllers/Settings/PreferencesController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import FormStatus from '@/components/form-status';
import InputError from '@/components/input-error';
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
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type MarketOption = {
    value: 'global_en' | 'ro_ro' | 'ru_ru';
    label: string;
};

type PageProps = {
    auth: Auth;
    mustVerifyEmail: boolean;
    status?: string;
    preferenceOptions: {
        markets: MarketOption[];
        timezones: string[];
        resultDepths: number[];
    };
};

export default function Profile({
    mustVerifyEmail,
    status,
    preferenceOptions,
}: PageProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <UserRound className="size-5" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Personal details</CardTitle>
                            <CardDescription>
                                Keep the name and email used for this local
                                account up to date.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form
                        {...ProfileController.update.form()}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                        className="space-y-5"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            defaultValue={auth.user.name}
                                            name="name"
                                            required
                                            autoComplete="name"
                                            placeholder="Full name"
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            defaultValue={auth.user.email}
                                            name="email"
                                            required
                                            autoComplete="username"
                                            placeholder="email@example.com"
                                            aria-invalid={Boolean(errors.email)}
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <FormStatus
                                            tone="info"
                                            title="Email verification needed"
                                            message="Verify your email address to unlock every authenticated workspace page."
                                        />
                                    )}

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div className="text-sm text-muted-foreground">
                                            Didn’t receive the message?{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                            >
                                                Send another verification email
                                            </Link>
                                            {status ===
                                                'verification-link-sent' && (
                                                <span className="ml-2 text-success-foreground">
                                                    Email sent.
                                                </span>
                                            )}
                                        </div>
                                    )}

                                <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save aria-hidden="true" />
                                        )}
                                        {processing
                                            ? 'Saving profile…'
                                            : 'Save profile'}
                                    </Button>
                                    {recentlySuccessful && (
                                        <span
                                            className="text-sm text-success-foreground"
                                            role="status"
                                        >
                                            Profile saved.
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-info/10 p-2 text-info-foreground">
                            <Globe2 className="size-5" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Research preferences</CardTitle>
                            <CardDescription>
                                Choose how dates are displayed and which market
                                new research starts with.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form
                        {...PreferencesController.update.form()}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                        className="space-y-5"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
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
                                            name="timezone"
                                            list="timezone-options"
                                            defaultValue={auth.user.timezone}
                                            required
                                            autoComplete="off"
                                            placeholder="Europe/Chisinau"
                                            aria-describedby="timezone-help"
                                            aria-invalid={Boolean(
                                                errors.timezone,
                                            )}
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
                                            Stored timestamps stay in UTC and
                                            display in this timezone.
                                        </p>
                                        <InputError message={errors.timezone} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="default_market_key">
                                            Default market
                                        </Label>
                                        <select
                                            id="default_market_key"
                                            name="default_market_key"
                                            defaultValue={
                                                auth.user.default_market_key ??
                                                ''
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            aria-describedby="market-help"
                                            aria-invalid={Boolean(
                                                errors.default_market_key,
                                            )}
                                        >
                                            <option value="">
                                                Ask me each time
                                            </option>
                                            {preferenceOptions.markets.map(
                                                (market) => (
                                                    <option
                                                        key={market.value}
                                                        value={market.value}
                                                    >
                                                        {market.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <p
                                            id="market-help"
                                            className="text-xs leading-5 text-muted-foreground"
                                        >
                                            You can still select another market
                                            for any individual run.
                                        </p>
                                        <InputError
                                            message={errors.default_market_key}
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
                                            name="default_result_depth"
                                            defaultValue={
                                                auth.user.default_result_depth
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            aria-describedby="depth-help"
                                            aria-invalid={Boolean(
                                                errors.default_result_depth,
                                            )}
                                        >
                                            {preferenceOptions.resultDepths.map(
                                                (depth) => (
                                                    <option
                                                        key={depth}
                                                        value={depth}
                                                    >
                                                        {depth} videos
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <p
                                            id="depth-help"
                                            className="text-xs leading-5 text-muted-foreground"
                                        >
                                            Higher depth uses more search calls
                                            and takes longer to enrich.
                                        </p>
                                        <InputError
                                            message={
                                                errors.default_result_depth
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                                    <Button
                                        disabled={processing}
                                        data-test="update-preferences-button"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save aria-hidden="true" />
                                        )}
                                        {processing
                                            ? 'Saving preferences…'
                                            : 'Save preferences'}
                                    </Button>
                                    {recentlySuccessful && (
                                        <span
                                            className="text-sm text-success-foreground"
                                            role="status"
                                        >
                                            Preferences saved.
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
