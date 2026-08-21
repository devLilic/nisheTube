import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Save, UserRound } from 'lucide-react';
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

type PageProps = {
    auth: Auth;
    mustVerifyEmail: boolean;
    status?: string;
};

export default function Profile({ mustVerifyEmail, status }: PageProps) {
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
