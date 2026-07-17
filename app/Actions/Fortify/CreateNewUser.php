<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditLogType;
use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use App\Services\Medical\MedicalInformationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Propaganistas\LaravelPhone\Rules\Phone;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private MedicalInformationService $medicalInformationService,
        private MedicalInformationRegistrationMatchService $registrationMatchService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'address' => ['required', 'string', 'max:1000'],
            'phone_number' => ['required', 'string', (new Phone)->international()],
            'phone_country_code' => ['required', 'string', 'max:5'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] ?? null,
                'last_name' => $input['last_name'],
                'suffix' => $input['suffix'] ?? null,
                'dob' => $input['dob'],
                'gender' => $input['gender'],
                'address' => $input['address'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
                'phone_country_code' => $input['phone_country_code'] ?? null,
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole(Role::User->value);

            $this->auditLogger->log(
                action: 'user.registered',
                type: AuditLogType::Create,
                actor: $user,
                subject: $user,
                channel: 'api',
            );

            // Registration always creates a fresh interim record immediately
            // and never blocks on, or reveals the outcome of, a name/dob
            // match - matching a name+dob alone is not proof of identity.
            // If a match against an already-claimed (primary-owned) record
            // is found, the record's primary user is notified out-of-band
            // and can accept/deny without the registrant ever learning
            // whether a match exists.
            $medicalInformation = $this->medicalInformationService->createInterim(
                user: $user,
                nameFields: [
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'suffix' => $user->suffix,
                ],
                dob: $input['dob'],
                gender: $input['gender'],
            );

            $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

            $this->registrationMatchService->detectAndNotify($user, [
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'suffix' => $user->suffix,
            ], (string) $user->dob->toDateString());

            return $user;
        });
    }
}
