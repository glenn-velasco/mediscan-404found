import { getCountries, getCountryCallingCode } from 'libphonenumber-js';
import type { CountryCode } from 'libphonenumber-js';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export const countries = getCountries()
    .map((code) => ({
        code,
        callingCode: `+${getCountryCallingCode(code as CountryCode)}`,
        label:
            new Intl.DisplayNames(['en'], { type: 'region' }).of(code) ?? code,
    }))
    .sort((a, b) => a.label.localeCompare(b.label));

interface PhoneInputProps {
    idPrefix: string;
    countryValue: string;
    phoneValue: string;
    onCountryChange: (v: string) => void;
    onPhoneChange: (v: string) => void;
    countryError?: string;
    phoneError?: string;
}

export function PhoneInput({
    idPrefix,
    countryValue,
    phoneValue,
    onCountryChange,
    onPhoneChange,
    countryError,
    phoneError,
}: PhoneInputProps) {
    return (
        <div className="flex gap-2">
            <div className="w-44 shrink-0">
                <Select value={countryValue} onValueChange={onCountryChange}>
                    <SelectTrigger id={`${idPrefix}_country`}>
                        <SelectValue placeholder="Country" />
                    </SelectTrigger>
                    <SelectContent>
                        {countries.map((c) => (
                            <SelectItem key={c.code} value={c.code}>
                                {c.label} ({c.callingCode})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={countryError} />
            </div>
            <div className="flex-1">
                <Input
                    id={`${idPrefix}_phone`}
                    type="tel"
                    value={phoneValue}
                    onChange={(e) => onPhoneChange(e.target.value)}
                    placeholder="Phone number"
                />
                <InputError message={phoneError} />
            </div>
        </div>
    );
}
