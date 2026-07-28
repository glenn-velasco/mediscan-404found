<?php

namespace App\Http\Requests;

use App\Enums\DiagnosisSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDiagnosisRequest extends FormRequest
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
            'id' => ['required', 'uuid', 'unique:diagnoses,id'],
            'condition' => ['required', 'string', 'min:1', 'max:255'],
            'date_of_diagnosis' => ['nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'severity' => ['nullable', new Enum(DiagnosisSeverity::class)],
            'diagnosed_by' => ['nullable', 'array'],
            'diagnosed_by.id' => ['required_with:diagnosed_by', 'integer', 'exists:users,id'],
            'diagnosed_by.fullname' => ['required_with:diagnosed_by', 'string', 'max:255'],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
