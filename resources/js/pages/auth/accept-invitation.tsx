import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { RegistrationFormFields } from '@/components/registration-form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import invitation from '@/routes/invitation';

interface AcceptInvitationProps {
    email: string;
    token: string;
    passwordRules?: string;
}

export default function AcceptInvitation({
    email,
    token,
    passwordRules,
}: AcceptInvitationProps) {
    const { data, setData, post, processing, errors } = useForm({
        username: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        dob: '',
        gender: '',
        address: '',
        phone_number: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(invitation.store(token).url);
    }

    return (
        <>
            <Head title="Accept Invitation" />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <RegistrationFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    email={email}
                    passwordRules={passwordRules}
                />

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <Spinner />}
                    Create account
                </Button>
            </form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept your invitation',
    description: 'Complete your profile to activate your account',
};
