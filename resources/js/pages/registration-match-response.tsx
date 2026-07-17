import { Head } from '@inertiajs/react';
import { CircleCheck, CircleX } from 'lucide-react';

export default function RegistrationMatchResponse({
    outcome,
}: {
    outcome: 'accepted' | 'denied';
}) {
    const accepted = outcome === 'accepted';

    return (
        <>
            <Head title={accepted ? 'Request accepted' : 'Request denied'} />

            <div className="flex flex-col items-center gap-4 text-center">
                {accepted ? (
                    <CircleCheck className="h-10 w-10 text-green-600" />
                ) : (
                    <CircleX className="h-10 w-10 text-muted-foreground" />
                )}
                <p className="text-sm text-muted-foreground">
                    {accepted
                        ? 'Thanks for confirming. The new registration is now linked to your medical record.'
                        : 'Thanks for letting us know. No changes were made to your medical record.'}
                </p>
                <p className="text-sm text-muted-foreground">
                    You can now close this page.
                </p>
            </div>
        </>
    );
}

RegistrationMatchResponse.layout = (props: {
    outcome: 'accepted' | 'denied';
}) => ({
    title: props.outcome === 'accepted' ? 'Request accepted' : 'Request denied',
});
