<?php

namespace App\Http\Requests;

use App\Enums\AllergySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allergen' => ['required', 'string', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', new Enum(AllergySeverity::class)],
        ];
    }
}
