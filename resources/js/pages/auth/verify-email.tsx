// Components
import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({
    status,
    emailChangeBackUrl,
    emailChangeBackLabel,
    verified,
}: {
    status?: string;
    emailChangeBackUrl?: string | null;
    emailChangeBackLabel?: string | null;
    verified?: boolean;
}) {
    if (verified) {
        return (
            <>
                <Head title="Email verified" />

                <div className="flex flex-col items-center gap-4 text-center">
                    <CircleCheck className="h-10 w-10 text-green-600" />
                    <p className="text-sm text-muted-foreground">
                        You can now close this page and return to the app.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to your email address.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>

                        {emailChangeBackUrl && (
                            <TextLink
                                href={emailChangeBackUrl}
                                className="mx-auto block text-sm"
                            >
                                {emailChangeBackLabel}
                            </TextLink>
                        )}

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = (props: { verified?: boolean }) => ({
    title: props.verified ? 'Email verified' : 'Email verification',
    description: props.verified
        ? 'Your email address has been verified successfully.'
        : 'Please verify your email address by clicking on the link we just emailed to you.',
});
