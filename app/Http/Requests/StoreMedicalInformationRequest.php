<?php

namespace App\Http\Requests;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\NameSuffix;
use App\Enums\RelationToPatient;
use App\Enums\Religion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'trim', 'min:1', 'max:255'],
            'middle_name' => ['nullable', 'string', 'trim', 'max:255'],
            'last_name' => ['required', 'string', 'trim', 'min:1', 'max:255'],
            'suffix' => ['nullable', 'string', Rule::in(NameSuffix::values())],

            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', 'string', Rule::in(Gender::values())],
            'blood_type' => ['nullable', 'string', Rule::in(BloodType::values())],
            'religion' => ['nullable', 'string', Rule::in(Religion::values())],

            'phone_country_code' => ['nullable', 'string', 'trim', 'max:5'],
            'phone' => ['nullable', 'string', 'trim', 'regex:/^[\d\s\-+()]*$/', 'max:32'],

            'address.province' => ['required', 'string', 'trim', 'max:255'],
            'address.street' => ['required', 'string', 'trim', 'max:255'],
            'address.unit' => ['required', 'string', 'trim', 'max:255'],
            'address.country' => ['required', 'string', 'trim', 'max:255'],
            'address.postal_code' => ['required', 'string', 'trim', 'max:20'],
            'address.city' => ['required', 'string', 'trim', 'max:255'],

            'no_blood_transfusion' => ['sometimes', 'boolean'],

            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'trim', 'min:1', 'max:255'],
            'contacts.*.relationship' => ['nullable', 'string', Rule::in(RelationToPatient::values())],
            'contacts.*.phone_number' => ['required_with:contacts', 'string', 'trim', 'regex:/^[\d\s\-+()]*$/', 'max:32'],
            'contacts.*.phone_country_code' => ['required_with:contacts', 'string', 'trim', 'max:5'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],

            'transfusion_consents' => ['sometimes', 'array'],
            'transfusion_consents.*.consenter_name' => ['required_with:transfusion_consents', 'string', 'trim', 'min:1', 'max:255'],
            'transfusion_consents.*.relationship_to_patient' => ['nullable', 'string', Rule::in(RelationToPatient::values())],
            'transfusion_consents.*.consented_at' => ['required_with:transfusion_consents', 'date'],
        ];
    }
}
