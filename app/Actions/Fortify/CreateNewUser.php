<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'username' => $input['username'],
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] ?? null,
                'last_name' => $input['last_name'],
                'suffix' => $input['suffix'] ?? null,
                'dob' => $input['dob'],
                'gender' => $input['gender'],
                'address' => $input['address'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole(Role::User->value);

            return $user;
        });
    }
}
