import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { countries } from '@/components/phone-input';
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
    const form = useForm({
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        dob: '',
        gender: '',
        address: '',
        phone_number: '',
        phone_country_code: '',
        street: '',
        unit: '',
        city: '',
        province: '',
        postal_code: '',
        country: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        const country = countries.find(
            (c) => c.code === form.data.phone_country_code,
        );

        form.transform((data) => ({
            ...data,
            phone_number: data.phone_number
                ? `${country?.callingCode ?? ''} ${data.phone_number}`.trim()
                : '',
            address: [
                data.street,
                data.unit,
                data.city,
                data.province,
                data.postal_code,
                data.country,
            ].join(', '),
        }));

        form.post(invitation.store(token).url);
    }

    return (
        <>
            <Head title="Accept Invitation" />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <RegistrationFormFields
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors}
                    email={email}
                    passwordRules={passwordRules}
                />

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
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
