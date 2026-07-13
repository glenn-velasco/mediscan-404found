import InputError from '@/components/input-error';
import { countries } from '@/components/phone-input';
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

interface AddressFormData {
    street: string;
    unit: string;
    city: string;
    province: string;
    postal_code: string;
    country: string;
}

interface AddressInputProps {
    data: AddressFormData;
    setData: <K extends keyof AddressFormData>(
        key: K,
        value: AddressFormData[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    addressError?: string;
}

export function AddressInput({
    data,
    setData,
    errors,
    addressError,
}: AddressInputProps) {
    return (
        <>
            <div className="col-span-2">
                <Separator />
            </div>

            <div className="col-span-2">
                <p className="text-sm font-medium text-muted-foreground">
                    Address
                </p>
                <Separator className="mt-2" />
            </div>

            {addressError && (
                <div className="col-span-2">
                    <InputError message={addressError} />
                </div>
            )}

            <div className="grid gap-1.5">
                <Label htmlFor="street">
                    Street <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="street"
                    value={data.street}
                    onChange={(e) => setData('street', e.target.value)}
                    autoComplete="street-address"
                />
                <InputError message={errors.street} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="unit">
                    Unit/Building/House{' '}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="unit"
                    value={data.unit}
                    onChange={(e) => setData('unit', e.target.value)}
                />
                <InputError message={errors.unit} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="city">
                    City <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="city"
                    value={data.city}
                    onChange={(e) => setData('city', e.target.value)}
                    autoComplete="address-level2"
                />
                <InputError message={errors.city} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="province">
                    Province <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="province"
                    value={data.province}
                    onChange={(e) => setData('province', e.target.value)}
                    autoComplete="address-level1"
                />
                <InputError message={errors.province} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="postal_code">
                    Postal Code <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="postal_code"
                    value={data.postal_code}
                    onChange={(e) => setData('postal_code', e.target.value)}
                    autoComplete="postal-code"
                />
                <InputError message={errors.postal_code} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="country">
                    Country <span className="text-destructive">*</span>
                </Label>
                <Select
                    value={data.country}
                    onValueChange={(v) => setData('country', v)}
                >
                    <SelectTrigger id="country">
                        <SelectValue placeholder="Select country" />
                    </SelectTrigger>
                    <SelectContent>
                        {countries.map((c) => (
                            <SelectItem key={c.code} value={c.code}>
                                {c.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.country} />
            </div>

            <div className="col-span-2">
                <Separator />
            </div>
        </>
    );
}
