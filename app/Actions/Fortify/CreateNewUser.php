<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Propaganistas\LaravelPhone\Rules\Phone;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $phoneCountry = $input['phone_country_code'] ?? null;
        $ecPhoneCountry = $input['emergency_contact_phone_country_code'] ?? null;

        $phoneRules = ['nullable', 'string'];
        if ($phoneCountry) {
            $phoneRules[] = (new Phone)->country($phoneCountry);
        }

        $ecPhoneRules = ['nullable', 'string'];
        if ($ecPhoneCountry) {
            $ecPhoneRules[] = (new Phone)->country($ecPhoneCountry);
        }

        Validator::make($input, [
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),

            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', new Enum(Gender::class)],

            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'phone' => $phoneRules,

            'blood_type' => ['nullable', new Enum(BloodType::class)],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'no_blood_transfusion' => ['boolean'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone_country_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_phone' => $ecPhoneRules,
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
        ])->validate();

        return DB::transaction(function () use ($input, $phoneCountry, $ecPhoneCountry) {
            $user = User::create([
                'name' => trim("{$input['first_name']} {$input['last_name']}"),
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $medicalInfo = $user->medicalInformation()->create([
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] ?? null,
                'last_name' => $input['last_name'],
                'suffix' => $input['suffix'] ?? null,
                'date_of_birth' => $input['date_of_birth'],
                'gender' => $input['gender'],
                'phone_country_code' => $phoneCountry,
                'phone' => $input['phone'] ?? null,
                'email' => $input['email'],
                'address' => $input['address'] ?? null,
                'blood_type' => $input['blood_type'] ?? null,
                'religion' => $input['religion'] ?? null,
                'no_blood_transfusion' => (bool) ($input['no_blood_transfusion'] ?? false),
            ]);

            if (! empty($input['emergency_contact_name'])) {
                $medicalInfo->emergencyContacts()->create([
                    'name' => $input['emergency_contact_name'],
                    'relationship' => $input['emergency_contact_relationship'] ?? null,
                    'phone_country_code' => $ecPhoneCountry,
                    'phone' => $input['emergency_contact_phone'] ?? null,
                    'is_primary' => true,
                ]);
            }

            $user->assignRole(Role::User->value);

            return $user;
        });
    }
}
