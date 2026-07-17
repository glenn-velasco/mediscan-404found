<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRetrievalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'old_email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'id_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        ];
    }
}
