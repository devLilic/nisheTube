import { Form, Head, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CloudCog,
    KeyRound,
    RefreshCw,
    ShieldCheck,
} from 'lucide-react';
import YouTubeIntegrationController from '@/actions/App/Http/Controllers/Settings/YouTubeIntegrationController';
import { QuotaMeter } from '@/components/quota-meter';
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
import { Spinner } from '@/components/ui/spinner';
import { edit as editYouTube } from '@/routes/youtube';
import type { Auth, QuotaSummary } from '@/types';

type ConnectionResult = {
    status: 'success' | 'error';
    title: string;
    message: string;
    checkedAt: string;
};

type PageProps = {
    auth: Auth;
    integration: {
        provider: string;
        keyConfigured: boolean;
    };
    connectionResult: ConnectionResult | null;
    youtubeQuota: QuotaSummary | null;
};

function formatTimestamp(value: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

export default function YouTubeSettings({
    integration,
    connectionResult,
}: PageProps) {
    const { auth, youtubeQuota } = usePage<PageProps>().props;

    return (
        <>
            <Head title="YouTube API settings" />

            <h1 className="sr-only">YouTube API settings</h1>

            <Card>
                <CardHeader>
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                <CloudCog
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>YouTube integration</CardTitle>
                                <CardDescription>
                                    Verify the local server-side connection
                                    without exposing the API key to the browser.
                                </CardDescription>
                            </div>
                        </div>
                        <Badge
                            variant="outline"
                            className={
                                integration.keyConfigured
                                    ? 'border-success/40 bg-success/10 text-success-foreground'
                                    : 'border-warning/40 bg-warning/10 text-warning-foreground'
                            }
                        >
                            {integration.keyConfigured ? (
                                <CheckCircle2 />
                            ) : (
                                <AlertTriangle />
                            )}
                            {integration.keyConfigured
                                ? 'Key configured'
                                : 'Key missing'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="rounded-lg border bg-muted/25 p-4">
                        <div className="flex items-start gap-3">
                            <KeyRound
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div className="space-y-1 text-sm">
                                <p className="font-medium">
                                    {integration.provider}
                                </p>
                                <p className="leading-6 text-muted-foreground">
                                    Store the key only as{' '}
                                    <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                        YOUTUBE_API_KEY
                                    </code>{' '}
                                    in the local <code>.env</code> file. The key
                                    value is never rendered here.
                                </p>
                            </div>
                        </div>
                    </div>

                    {connectionResult && (
                        <Alert
                            variant={
                                connectionResult.status === 'error'
                                    ? 'destructive'
                                    : 'default'
                            }
                            className={
                                connectionResult.status === 'success'
                                    ? 'border-success/40 bg-success/10 text-success-foreground'
                                    : undefined
                            }
                        >
                            {connectionResult.status === 'success' ? (
                                <CheckCircle2 aria-hidden="true" />
                            ) : (
                                <AlertTriangle aria-hidden="true" />
                            )}
                            <AlertTitle>{connectionResult.title}</AlertTitle>
                            <AlertDescription className="text-current/80">
                                <p>{connectionResult.message}</p>
                                <p className="text-xs">
                                    Checked{' '}
                                    {formatTimestamp(
                                        connectionResult.checkedAt,
                                        auth.user.timezone,
                                    )}
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Form
                        {...YouTubeIntegrationController.test.form()}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                    >
                        {({ processing }) => (
                            <Button disabled={processing}>
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <RefreshCw aria-hidden="true" />
                                )}
                                {processing
                                    ? 'Testing connection…'
                                    : 'Test connection'}
                            </Button>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-info/10 p-2 text-info-foreground">
                            <ShieldCheck
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Today’s quota estimate</CardTitle>
                            <CardDescription>
                                Project-wide usage recorded by this NisheTube
                                installation.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    {youtubeQuota ? (
                        <>
                            <div className="grid gap-5 xl:grid-cols-2">
                                {youtubeQuota.buckets.map((bucket) => (
                                    <div
                                        key={bucket.bucket}
                                        className="rounded-lg border p-4"
                                    >
                                        <QuotaMeter summary={bucket} />
                                    </div>
                                ))}
                            </div>
                            <Alert className="border-info/35 bg-info/10 text-info-foreground">
                                <ShieldCheck aria-hidden="true" />
                                <AlertTitle>NisheTube estimate</AlertTitle>
                                <AlertDescription className="text-current/80">
                                    Resets at{' '}
                                    {formatTimestamp(
                                        youtubeQuota.reset_at,
                                        auth.user.timezone,
                                    )}
                                    . Google Cloud Console is authoritative
                                    because other apps can consume the same
                                    project quota.
                                </AlertDescription>
                            </Alert>
                        </>
                    ) : (
                        <Alert>
                            <AlertTriangle aria-hidden="true" />
                            <AlertTitle>Quota data unavailable</AlertTitle>
                            <AlertDescription>
                                Refresh the page or verify the local database
                                configuration.
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

YouTubeSettings.layout = {
    breadcrumbs: [
        {
            title: 'YouTube API settings',
            href: editYouTube(),
        },
    ],
};
