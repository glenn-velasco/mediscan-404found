<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'first_name' => ['sometimes', ...$this->firstNameRules()],
            'middle_name' => $this->middleNameRules(),
            'last_name' => ['sometimes', ...$this->lastNameRules()],
            'suffix' => $this->suffixRules(),
            'dob' => ['sometimes', ...$this->dobRules()],
            'gender' => ['sometimes', ...$this->genderRules()],
            'address' => $this->addressRules(),
            'phone_number' => $this->phoneNumberRules(),
            'phone_country_code' => $this->phoneCountryCodeRules(),
        ];
    }
}
