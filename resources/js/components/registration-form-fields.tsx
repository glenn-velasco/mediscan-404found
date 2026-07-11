import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { genderOptions } from '@/types/gender';

export interface RegistrationFormData {
    username: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string;
    dob: string;
    gender: string;
    address: string;
    phone_number: string;
    password: string;
    password_confirmation: string;
}

interface Props {
    data: RegistrationFormData;
    setData: <K extends keyof RegistrationFormData>(
        key: K,
        value: RegistrationFormData[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    email: string;
    onEmailChange?: (value: string) => void;
    passwordRules?: string;
}

function SectionHeading({ children }: { children: ReactNode }) {
    return (
        <div className="col-span-2">
            <p className="text-sm font-medium text-muted-foreground">
                {children}
            </p>
            <Separator className="mt-2" />
        </div>
    );
}

export function RegistrationFormFields({
    data,
    setData,
    errors,
    email,
    onEmailChange,
    passwordRules,
}: Props) {
    const editable = onEmailChange !== undefined;

    return (
        <Card>
            <CardContent className="grid grid-cols-2 items-start gap-x-4 gap-y-4 pt-6">
                {/* Account */}
                <SectionHeading>Account</SectionHeading>

                <div className="grid gap-1.5">
                    <Label htmlFor="username">
                        Username <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="username"
                        value={data.username}
                        onChange={(e) => setData('username', e.target.value)}
                        autoComplete="username"
                        autoFocus={editable}
                    />
                    <InputError message={errors.username} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="email">
                        Email address{' '}
                        {editable && (
                            <span className="text-destructive">*</span>
                        )}
                    </Label>
                    {editable ? (
                        <>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                value={email}
                                onChange={(e) => onEmailChange(e.target.value)}
                                placeholder="you@example.com"
                            />
                            <InputError message={errors.email} />
                        </>
                    ) : (
                        <Input
                            id="email"
                            type="email"
                            value={email}
                            disabled
                            readOnly
                            tabIndex={-1}
                        />
                    )}
                </div>

                {/* Personal details */}
                <SectionHeading>Personal details</SectionHeading>

                <div className="grid gap-1.5">
                    <Label htmlFor="first_name">
                        First name <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="first_name"
                        value={data.first_name}
                        onChange={(e) => setData('first_name', e.target.value)}
                        autoComplete="given-name"
                    />
                    <InputError message={errors.first_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="middle_name">Middle name</Label>
                    <Input
                        id="middle_name"
                        value={data.middle_name}
                        onChange={(e) => setData('middle_name', e.target.value)}
                        autoComplete="additional-name"
                    />
                    <InputError message={errors.middle_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="last_name">
                        Last name <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="last_name"
                        value={data.last_name}
                        onChange={(e) => setData('last_name', e.target.value)}
                        autoComplete="family-name"
                    />
                    <InputError message={errors.last_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="suffix">Suffix</Label>
                    <Input
                        id="suffix"
                        value={data.suffix}
                        onChange={(e) => setData('suffix', e.target.value)}
                        autoComplete="honorific-suffix"
                    />
                    <InputError message={errors.suffix} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="dob">
                        Date of birth{' '}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="dob"
                        type="date"
                        value={data.dob}
                        onChange={(e) => setData('dob', e.target.value)}
                        autoComplete="bday"
                    />
                    <InputError message={errors.dob} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="gender">
                        Gender <span className="text-destructive">*</span>
                    </Label>
                    <Select
                        value={data.gender}
                        onValueChange={(v) => setData('gender', v)}
                    >
                        <SelectTrigger id="gender">
                            <SelectValue placeholder="Select gender" />
                        </SelectTrigger>
                        <SelectContent>
                            {genderOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="phone_number">Phone number</Label>
                    <Input
                        id="phone_number"
                        type="tel"
                        value={data.phone_number}
                        onChange={(e) =>
                            setData('phone_number', e.target.value)
                        }
                        autoComplete="tel"
                    />
                    <InputError message={errors.phone_number} />
                </div>

                <div className="col-span-2 grid gap-1.5">
                    <Label htmlFor="address">Address</Label>
                    <Textarea
                        id="address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        autoComplete="street-address"
                    />
                    <InputError message={errors.address} />
                </div>

                <div className="col-span-2">
                    <Separator />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="password">
                        Password <span className="text-destructive">*</span>
                    </Label>
                    <PasswordInput
                        id="password"
                        autoFocus={!editable}
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Choose a password"
                        passwordrules={passwordRules}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="password_confirmation">
                        Confirm password{' '}
                        <span className="text-destructive">*</span>
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        placeholder="Confirm password"
                        passwordrules={passwordRules}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>
            </CardContent>
        </Card>
    );
}
