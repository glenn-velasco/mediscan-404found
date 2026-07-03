<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\BloodType;
use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Propaganistas\LaravelPhone\Rules\Phone;

class RegisterRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $phoneCountry = $this->input('phone_country_code');
        $ecPhoneCountry = $this->input('emergency_contact_phone_country_code');

        $phoneRules = ['nullable', 'string'];
        if ($phoneCountry) {
            $phoneRules[] = (new Phone)->country($phoneCountry);
        }

        $ecPhoneRules = ['nullable', 'string'];
        if ($ecPhoneCountry) {
            $ecPhoneRules[] = (new Phone)->country($ecPhoneCountry);
        }

        return [
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'device_name' => ['required', 'string'],

            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', new Enum(Gender::class)],

            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'phone' => $phoneRules,

            'blood_type' => ['nullable', new Enum(BloodType::class)],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'no_blood_transfusion' => ['boolean'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_phone' => $ecPhoneRules,
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
        ];
    }
}
