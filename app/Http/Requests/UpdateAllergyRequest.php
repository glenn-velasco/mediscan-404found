<?php

namespace App\Http\Requests;

use App\Enums\AllergySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAllergyRequest extends FormRequest
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
            'allergen' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['sometimes', 'required', new Enum(AllergySeverity::class)],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
