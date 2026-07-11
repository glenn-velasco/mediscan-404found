<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Role::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            if (! $target instanceof User) {
                return;
            }

            if ($this->user()?->is($target)) {
                $validator->errors()->add('role', 'You cannot change your own role.');

                return;
            }

            $newRole = $this->input('role');

            if ($target->hasRole(Role::Admin->value)
                && $newRole !== Role::Admin->value
                && User::query()->filterByRole(Role::Admin->value)->count() <= 1) {
                $validator->errors()->add('role', 'You cannot remove the last remaining admin.');
            }
        });
    }
}
