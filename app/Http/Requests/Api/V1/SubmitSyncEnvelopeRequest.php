<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EnvelopeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SubmitSyncEnvelopeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ciphertext' => ['required', 'string'],
            'envelope_type' => ['required', new Enum(EnvelopeType::class)],
            'item_identifier' => [
                'nullable',
                'string',
                'max:500',
                'required_if:envelope_type,allergy_verification,condition_verification,diagnosis_verification,medication_verification,emergency_contact_verification',
            ],
            'verified' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'item_identifier.required_if' => 'The item identifier is required for verification envelopes.',
        ];
    }
}
