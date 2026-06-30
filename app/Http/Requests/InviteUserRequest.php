<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            'role'            => ['required', 'string', new Enum(Role::class)],
            'expires_in_days' => ['required', 'integer', Rule::in([1, 3, 7, 14, 30])],
        ];
    }
}
