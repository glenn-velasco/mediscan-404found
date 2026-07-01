import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/use-auth';
import { edit } from '@/routes/account';

export default function Account() {
    const { user } = useAuth();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const form = useForm({ email: user.email });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (form.data.email === user.email) {
            confirmSubmit();

            return;
        }

        setConfirmOpen(true);
    }

    function confirmSubmit() {
        form.put(AccountController.update.url(), {
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
        });
    }

    function cancelSubmit() {
        form.setData('email', user.email);
        form.clearErrors('email');
    }

    return (
        <>
            <Head title="Account settings" />

            <h1 className="sr-only">Account settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Update email"
                    description="Changing your email requires verifying the new address before it is fully active"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>

                        <Input
                            id="email"
                            type="email"
                            name="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            className="mt-1 block w-full"
                            autoComplete="email"
                            placeholder="you@example.com"
                            required
                        />

                        <InputError message={form.errors.email} />

                        {!user.email_verified_at && (
                            <p className="text-sm text-muted-foreground">
                                Your email address is unverified.
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={
                                form.processing ||
                                form.data.email === user.email
                            }
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Change email address?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            You&apos;ll need to verify {form.data.email} before
                            it becomes active. Your account will show as
                            unverified until you do.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={cancelSubmit}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={form.processing}
                            onClick={confirmSubmit}
                        >
                            Continue
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Account.layout = {
    breadcrumbs: [
        {
            title: 'Account settings',
            href: edit(),
        },
    ],
};
