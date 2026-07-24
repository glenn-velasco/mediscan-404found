<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationRequest extends FormRequest
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
            'id' => ['required', 'uuid', 'unique:medications,id'],
            'name' => ['required', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
