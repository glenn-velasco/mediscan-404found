<?php

namespace App\Http\Requests;

use App\Enums\RelationToPatient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', new Enum(RelationToPatient::class)],
            'phone_country_code' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
