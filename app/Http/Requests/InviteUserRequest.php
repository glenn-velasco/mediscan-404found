<?php

namespace App\Http\Requests;

use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                function (string $_attribute, mixed $value, \Closure $fail) {
                    $hasPending = UserInvitation::where('email', $value)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($hasPending) {
                        $fail('A pending invitation has already been sent to this email.');
                    }
                },
            ],
            'expires_in_days' => ['required', 'integer', Rule::in([1, 3, 7, 14, 30])],
        ];
    }
}
