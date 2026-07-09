<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\Rules\Phone;

class UpdateEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $phoneCountry = $this->normalizePhoneCountry($this->input('phone_country_code'));

        $phoneRules = ['nullable', 'string'];
        if ($phoneCountry) {
            $phoneRules[] = (new Phone)->country($phoneCountry);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'phone' => $phoneRules,
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizePhoneCountry(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (strlen($value) === 2 && ctype_alpha($value)) {
            return strtoupper($value);
        }

        $dialCode = (int) ltrim($value, '+');
        if ($dialCode > 0) {
            return PhoneNumberUtil::getInstance()->getRegionCodeForCountryCode($dialCode);
        }

        return null;
    }
}
