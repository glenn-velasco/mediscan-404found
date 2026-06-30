<?php

namespace App\Http\Requests;

use App\Enums\BloodType;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Propaganistas\LaravelPhone\Rules\Phone;

class UpdateMedicalInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $phoneCountry = $this->input('phone_country_code');

        $phoneRules = ['nullable', 'string'];
        if ($phoneCountry) {
            $phoneRules[] = (new Phone())->country($phoneCountry);
        }

        return [
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore(auth()->id())],

            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'suffix'       => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender'        => ['required', new Enum(Gender::class)],

            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'phone'              => $phoneRules,

            'blood_type'           => ['nullable', new Enum(BloodType::class)],
            'religion'             => ['nullable', 'string', 'max:100'],
            'address'              => ['nullable', 'string', 'max:1000'],
            'no_blood_transfusion' => ['boolean'],
        ];
    }
}
