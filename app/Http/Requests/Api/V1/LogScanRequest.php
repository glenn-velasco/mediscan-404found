<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ScanContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogScanRequest extends FormRequest
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
            'scanned_user_id' => ['required', 'integer', 'exists:users,id'],
            'context' => ['nullable', 'string', Rule::in(ScanContext::values())],
        ];
    }
}
