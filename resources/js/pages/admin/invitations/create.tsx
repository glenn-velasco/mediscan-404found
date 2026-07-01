import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Role, roleOptions } from '@/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import admin from '@/routes/admin';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        role: Role.User as string,
        expires_in_days: 3,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(admin.invitations.store());
    }

    return (
        <>
            <Head title="Send Invitation" />
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                    />
                    <InputError message={errors.email} />
                </div>

                <div>
                    <Label htmlFor="role">Role</Label>
                    <Select value={data.role} onValueChange={(v) => setData('role', v)}>
                        <SelectTrigger id="role">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roleOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.role} />
                </div>

                <div>
                    <Label htmlFor="expires_in_days">Expires in (days)</Label>
                    <Select
                        value={String(data.expires_in_days)}
                        onValueChange={(v) => setData('expires_in_days', Number(v))}
                    >
                        <SelectTrigger id="expires_in_days">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[1, 3, 7, 14, 30].map((d) => (
                                <SelectItem key={d} value={String(d)}>
                                    {d} {d === 1 ? 'day' : 'days'}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.expires_in_days} />
                </div>

                <Button type="submit" disabled={processing}>
                    Send Invitation
                </Button>
            </form>
        </>
    );
}
