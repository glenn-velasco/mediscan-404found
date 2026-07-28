<?php

namespace App\Http\Requests;

use App\Enums\AllergySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAllergyRequest extends FormRequest
{
    use DecodesVerifiedBy;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForVerifiedByDecoding();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid', 'unique:allergies,id'],
            'allergen' => ['required', 'string', 'min:1', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', new Enum(AllergySeverity::class)],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
