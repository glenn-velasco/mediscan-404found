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
    const callingCode =
        countries.find((c) => c.code === countryValue)?.callingCode ?? '';

    const error = countryError ?? phoneError;

    return (
        <div>
            <div className="flex h-9 w-full rounded-md border shadow-xs has-[input:focus-visible]:border-ring has-[input:focus-visible]:ring-[3px] has-[input:focus-visible]:ring-ring/50">
                <div className="flex shrink-0 items-center border-r px-2">
                    <Select
                        value={countryValue}
                        onValueChange={onCountryChange}
                    >
                        <SelectTrigger
                            id={`${idPrefix}_country`}
                            className="flex items-center gap-1 border-0 bg-transparent p-0 shadow-none hover:bg-transparent focus-visible:ring-0 [&_svg]:opacity-50"
                        >
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
                    {callingCode && (
                        <span className="ml-1 text-sm text-muted-foreground select-none">
                            {callingCode}
                        </span>
                    )}
                </div>
                <Input
                    id={`${idPrefix}_phone`}
                    type="tel"
                    value={phoneValue}
                    onChange={(e) => onPhoneChange(e.target.value)}
                    placeholder="Phone number"
                    className="h-full min-w-0 rounded-none border-0 shadow-none focus-visible:ring-0"
                />
            </div>
            <InputError message={error} />
        </div>
    );
}
