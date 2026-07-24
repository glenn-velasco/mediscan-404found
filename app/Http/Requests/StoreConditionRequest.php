<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConditionRequest extends FormRequest
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
            // Client (mobile app)-generated UUID - see the `allergies`
            // migration's comment on why this becomes the record's real id.
            'id' => ['required', 'uuid', 'unique:conditions,id'],
            'description' => ['required', 'string', 'max:1000'],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
