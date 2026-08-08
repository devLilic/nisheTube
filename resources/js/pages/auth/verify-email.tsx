import { Form, Head } from '@inertiajs/react';
import FormStatus from '@/components/form-status';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            <FormStatus
                message={
                    status === 'verification-link-sent'
                        ? 'A fresh verification link was sent to your email address.'
                        : undefined
                }
                title="Email sent"
                className="mb-6"
            />

            <Form {...send.form()} disableWhileProcessing className="space-y-4">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            variant="secondary"
                            className="w-full"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Sending email…'
                                : 'Resend verification email'}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-center text-sm"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description:
        'Open the link we sent to activate your account and protect your research workspace.',
};
