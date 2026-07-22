<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditLogType;
use App\Enums\Role;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use App\Services\Medical\MedicalInformationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
     * Fortify's contract + used by invitation acceptance
     * (InvitationService::acceptInvitation()) - always materializes an
     * account immediately, no name/dob match check. Invited accounts (staff/
     * professionals) don't go through the self-service registration-match
     * flow, so there's nothing to stage here.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, $this->registrationRules())->validate();

        return DB::transaction(function () use ($input) {
            $user = $this->createUser($input);

            $medicalInformation = $this->medicalInformationService->createInterim(
                user: $user,
                nameFields: $this->nameFields($user),
                dob: $input['dob'],
                gender: $input['gender'],
            );

            $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

            return $user;
        });
    }

    /**
     * Used by self-service registration (AccountService::register()): checks
     * for a name+dob match against an already-claimed record *before*
     * creating anything. No match (the common case) proceeds exactly like
     * create() above. A real match holds the submitted data in a
     * PendingRegistration instead of creating a User/MedicalInformation at
     * all - nothing is ever created until the candidate record's primary
     * user accepts (MedicalInformationRegistrationMatchService::accept()),
     * so there's no account to block or delete if they deny/ignore it.
     *
     * @param  array<string, string>  $input
     * @return array{pending: true}|array{pending: false, user: User}
     */
    public function createOrStage(array $input): array
    {
        Validator::make($input, $this->registrationRules())->validate();

        return DB::transaction(function () use ($input) {
            $nameFields = [
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] ?? null,
                'last_name' => $input['last_name'],
                'suffix' => $input['suffix'] ?? null,
            ];

            $candidate = $this->medicalInformationService->findLinkCandidate($nameFields, $input['dob']);

            if ($candidate === null || $candidate->primary_user_id === null) {
                $user = $this->createUser($input);

                $medicalInformation = $this->medicalInformationService->createInterim(
                    user: $user,
                    nameFields: $nameFields,
                    dob: $input['dob'],
                    gender: $input['gender'],
                );

                $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

                return ['pending' => false, 'user' => $user];
            }

            $pendingRegistration = PendingRegistration::create([
                ...$nameFields,
                'dob' => $input['dob'],
                'gender' => $input['gender'],
                'address' => $input['address'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
                'phone_country_code' => $input['phone_country_code'] ?? null,
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $this->registrationMatchService->createForPendingRegistration($pendingRegistration, $candidate);

            return ['pending' => true];
        });
    }

    /** @return array<string, mixed> */
    private function registrationRules(): array
    {
        return [
            ...$this->profileRules(),
            'address' => ['required', 'string', 'max:1000'],
            'phone_number' => ['required', 'string', (new Phone)->international()],
            'phone_country_code' => ['required', 'string', 'max:5'],
            'password' => $this->passwordRules(),
        ];
    }

    /** @param  array<string, string>  $input */
    private function createUser(array $input): User
    {
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

        metric('signups')->category(Role::User->value)->hourly()->record();

        $this->auditLogger->log(
            action: 'user.registered',
            type: AuditLogType::Create,
            actor: $user,
            subject: $user,
            channel: 'api',
        );

        return $user;
    }

    /** @return array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string} */
    private function nameFields(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
        ];
    }
}
