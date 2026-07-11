<?php

namespace App\Concerns;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Propaganistas\LaravelPhone\Rules\Phone;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'username' => $this->usernameRules($userId),
            'first_name' => $this->firstNameRules(),
            'middle_name' => $this->middleNameRules(),
            'last_name' => $this->lastNameRules(),
            'suffix' => $this->suffixRules(),
            'dob' => $this->dobRules(),
            'gender' => $this->genderRules(),
            'address' => $this->addressRules(),
            'phone_number' => $this->phoneNumberRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate usernames.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function firstNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function middleNameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function lastNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function suffixRules(): array
    {
        return ['nullable', 'string', 'max:50'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function dobRules(): array
    {
        return ['required', 'date', 'before:today'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function genderRules(): array
    {
        return ['required', new Enum(Gender::class)];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function addressRules(): array
    {
        return ['nullable', 'string', 'max:1000'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneNumberRules(): array
    {
        return ['nullable', 'string', (new Phone)->international()];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
