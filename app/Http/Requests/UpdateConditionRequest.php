<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConditionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:1000'],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
