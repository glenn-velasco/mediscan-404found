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
            'condition' => ['required', 'string', 'max:255'],
            'date_of_diagnosis' => ['nullable', 'date', 'before_or_equal:today'],
            'severity' => ['nullable', new Enum(DiagnosisSeverity::class)],
            'verified_by' => ['nullable', 'array'],
        ];
    }
}
