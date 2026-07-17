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
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', Rule::in(NameSuffix::values())],

            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', Rule::in(Gender::values())],
            'blood_type' => ['nullable', 'string', Rule::in(BloodType::values())],
            'religion' => ['nullable', 'string', Rule::in(Religion::values())],
            'national_id' => ['nullable', 'string', 'max:255'],

            'address.province' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.unit' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.city' => ['nullable', 'string', 'max:255'],

            'no_blood_transfusion' => ['sometimes', 'boolean'],

            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.relationship' => ['nullable', 'string', Rule::in(RelationToPatient::values())],
            'contacts.*.phone_number' => ['required_with:contacts', 'string', 'max:32'],
            'contacts.*.phone_country_code' => ['required_with:contacts', 'string', 'max:5'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],

            'transfusion_consents' => ['sometimes', 'array'],
            'transfusion_consents.*.consenter_name' => ['required_with:transfusion_consents', 'string', 'max:255'],
            'transfusion_consents.*.relationship_to_patient' => ['nullable', 'string', Rule::in(RelationToPatient::values())],
            'transfusion_consents.*.consented_at' => ['required_with:transfusion_consents', 'date'],
        ];
    }
}
