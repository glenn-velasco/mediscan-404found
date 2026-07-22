<?php

namespace App\Http\Requests;

use App\Enums\AllergySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Client (mobile app)-generated UUID - see the `allergies`
            // migration's comment on why this becomes the record's real id.
            'id' => ['required', 'uuid', 'unique:allergies,id'],
            'allergen' => ['required', 'string', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', new Enum(AllergySeverity::class)],
        ];
    }
}
