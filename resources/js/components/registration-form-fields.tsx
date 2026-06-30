import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { PhoneInput } from '@/components/phone-input';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { BloodType } from '@/types';

export interface RegistrationFormData {
    password: string;
    password_confirmation: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string;
    date_of_birth: string;
    gender: string;
    phone_country_code: string;
    phone: string;
    blood_type: string;
    religion: string;
    address: string;
    no_blood_transfusion: boolean;
    emergency_contact_name: string;
    emergency_contact_phone_country_code: string;
    emergency_contact_phone: string;
    emergency_contact_relationship: string;
}

interface Props {
    data: RegistrationFormData;
    setData: <K extends keyof RegistrationFormData>(key: K, value: RegistrationFormData[K]) => void;
    errors: Partial<Record<string, string>>;
    email: string;
    onEmailChange?: (value: string) => void;
    passwordRules?: string;
}

function SectionHeading({ children }: { children: ReactNode }) {
    return (
        <div className="col-span-2">
            <p className="text-sm font-medium text-muted-foreground">{children}</p>
            <Separator className="mt-2" />
        </div>
    );
}

export function RegistrationFormFields({ data, setData, errors, email, onEmailChange, passwordRules }: Props) {
    const editable = onEmailChange !== undefined;

    return (
        <Card>
            <CardContent className="grid grid-cols-2 items-start gap-x-4 gap-y-4 pt-6">

                {/* Account */}
                <SectionHeading>Account</SectionHeading>

                <div className="col-span-2 grid gap-1.5">
                    <Label htmlFor="email">
                        Email address {editable && <span className="text-destructive">*</span>}
                    </Label>
                    {editable ? (
                        <>
                            <Input
                                id="email"
                                type="email"
                                autoFocus
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

                <div className="grid gap-1.5">
                    <Label htmlFor="password">Password <span className="text-destructive">*</span></Label>
                    <PasswordInput
                        id="password"
                        autoFocus={!editable}
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder={editable ? 'Password' : 'Choose a password'}
                        passwordrules={passwordRules}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="password_confirmation">Confirm password <span className="text-destructive">*</span></Label>
                    <PasswordInput
                        id="password_confirmation"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder="Confirm password"
                        passwordrules={passwordRules}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                {/* Personal Information */}
                <SectionHeading>Personal Information</SectionHeading>

                <div className="grid gap-1.5">
                    <Label htmlFor="first_name">First name <span className="text-destructive">*</span></Label>
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
                    <Label htmlFor="last_name">Last name <span className="text-destructive">*</span></Label>
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
                        placeholder="Jr., Sr., III…"
                    />
                    <InputError message={errors.suffix} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="date_of_birth">Date of birth <span className="text-destructive">*</span></Label>
                    <Input
                        id="date_of_birth"
                        type="date"
                        value={data.date_of_birth}
                        onChange={(e) => setData('date_of_birth', e.target.value)}
                    />
                    <InputError message={errors.date_of_birth} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="gender">Gender <span className="text-destructive">*</span></Label>
                    <Select value={data.gender} onValueChange={(v) => setData('gender', v)}>
                        <SelectTrigger id="gender"><SelectValue placeholder="Select…" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="male">Male</SelectItem>
                            <SelectItem value="female">Female</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                {/* Medical Details */}
                <SectionHeading>Medical Details</SectionHeading>

                <div className="col-span-2 grid gap-1.5">
                    <Label>Phone number</Label>
                    <PhoneInput
                        idPrefix="phone"
                        countryValue={data.phone_country_code}
                        phoneValue={data.phone}
                        onCountryChange={(v) => setData('phone_country_code', v)}
                        onPhoneChange={(v) => setData('phone', v)}
                        countryError={errors.phone_country_code}
                        phoneError={errors.phone}
                    />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="blood_type">Blood type</Label>
                    <Select value={data.blood_type} onValueChange={(v) => setData('blood_type', v)}>
                        <SelectTrigger id="blood_type"><SelectValue placeholder="Unknown" /></SelectTrigger>
                        <SelectContent>
                            {(Object.values(BloodType) as BloodType[]).map((bt) => (
                                <SelectItem key={bt} value={bt}>{bt}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.blood_type} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="religion">Religion</Label>
                    <Input
                        id="religion"
                        value={data.religion}
                        onChange={(e) => setData('religion', e.target.value)}
                    />
                    <InputError message={errors.religion} />
                </div>

                <div className="col-span-2 grid gap-1.5">
                    <Label htmlFor="address">Address</Label>
                    <Input
                        id="address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                    <InputError message={errors.address} />
                </div>

                <div className="col-span-2 flex items-center gap-2">
                    <Checkbox
                        id="no_blood_transfusion"
                        checked={data.no_blood_transfusion}
                        onCheckedChange={(v) => setData('no_blood_transfusion', Boolean(v))}
                    />
                    <Label htmlFor="no_blood_transfusion" className="cursor-pointer font-normal">
                        No blood transfusion (religious / personal objection)
                    </Label>
                </div>

                {/* Emergency Contact */}
                <SectionHeading>Emergency Contact</SectionHeading>

                <div className="grid gap-1.5">
                    <Label htmlFor="ec_name">Contact name</Label>
                    <Input
                        id="ec_name"
                        value={data.emergency_contact_name}
                        onChange={(e) => setData('emergency_contact_name', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.emergency_contact_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="ec_relationship">Relationship</Label>
                    <Input
                        id="ec_relationship"
                        value={data.emergency_contact_relationship}
                        onChange={(e) => setData('emergency_contact_relationship', e.target.value)}
                        placeholder="e.g. Spouse, Parent"
                    />
                    <InputError message={errors.emergency_contact_relationship} />
                </div>

                <div className="col-span-2 grid gap-1.5">
                    <Label>Phone number</Label>
                    <PhoneInput
                        idPrefix="ec_phone"
                        countryValue={data.emergency_contact_phone_country_code}
                        phoneValue={data.emergency_contact_phone}
                        onCountryChange={(v) => setData('emergency_contact_phone_country_code', v)}
                        onPhoneChange={(v) => setData('emergency_contact_phone', v)}
                        countryError={errors.emergency_contact_phone_country_code}
                        phoneError={errors.emergency_contact_phone}
                    />
                </div>

            </CardContent>
        </Card>
    );
}
