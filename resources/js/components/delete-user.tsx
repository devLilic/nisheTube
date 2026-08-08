import { Form } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Card className="border-destructive/30">
            <CardHeader>
                <div className="flex items-start gap-3">
                    <div className="rounded-lg bg-destructive/10 p-2 text-destructive">
                        <AlertTriangle className="size-5" aria-hidden="true" />
                    </div>
                    <div className="space-y-1">
                        <CardTitle>Delete account</CardTitle>
                        <CardDescription>
                            Permanently remove this account and all research
                            data that belongs to it.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-4 rounded-xl border border-destructive/25 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm font-medium">
                            This action cannot be undone.
                        </p>
                        <p className="mt-1 text-sm leading-5 text-muted-foreground">
                            You will need your current password to confirm.
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                data-test="delete-user-button"
                            >
                                <Trash2 aria-hidden="true" />
                                Delete account
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Permanently delete your account?
                            </DialogTitle>
                            <DialogDescription>
                                This permanently deletes your NisheTube account
                                and all resources owned by it. Enter your
                                password to confirm.
                            </DialogDescription>

                            <Form
                                {...ProfileController.destroy.form()}
                                options={{ preserveScroll: true }}
                                onError={() => passwordInput.current?.focus()}
                                resetOnSuccess
                                disableWhileProcessing
                                className="space-y-6"
                            >
                                {({
                                    resetAndClearErrors,
                                    processing,
                                    errors,
                                }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="delete-password">
                                                Current password
                                            </Label>
                                            <PasswordInput
                                                id="delete-password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Enter current password"
                                                autoComplete="current-password"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.password,
                                                )}
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        resetAndClearErrors()
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                                data-test="confirm-delete-user-button"
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Trash2 aria-hidden="true" />
                                                )}
                                                {processing
                                                    ? 'Deleting account…'
                                                    : 'Delete permanently'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    );
}
