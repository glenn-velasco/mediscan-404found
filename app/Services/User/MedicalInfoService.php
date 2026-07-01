<?php

namespace App\Services\User;

use App\Models\Allergy;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Repositories\Eloquent\MedicalInformationRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class MedicalInfoService
{
    public function __construct(
        private MedicalInformationRepository $repository,
        private AccountService $accountService,
    ) {}

    /** @return array<string, mixed>|null */
    public function show(User $user): ?array
    {
        $medicalInfo = $this->repository->findWithRelationsByUser($user);

        if (! $medicalInfo) {
            return null;
        }

        return Cache::remember("user.{$user->id}.dashboard", now()->addMonth(), fn () => [
            'full_name' => $medicalInfo->full_name,
            'first_name' => $medicalInfo->first_name,
            'middle_name' => $medicalInfo->middle_name,
            'last_name' => $medicalInfo->last_name,
            'suffix' => $medicalInfo->suffix,
            'date_of_birth' => $medicalInfo->date_of_birth->toDateString(),
            'gender' => $medicalInfo->gender->value,
            'blood_type' => $medicalInfo->blood_type,
            'phone' => $medicalInfo->phone,
            'phone_country_code' => $medicalInfo->phone_country_code,
            'address' => $medicalInfo->address,
            'religion' => $medicalInfo->religion,
            'no_blood_transfusion' => $medicalInfo->no_blood_transfusion,
            'allergies' => $medicalInfo->allergies->map(fn (Allergy $allergy) => [
                'id' => $allergy->id,
                'allergen' => $allergy->allergen,
                'reaction' => $allergy->reaction,
                'severity' => $allergy->severity->value,
            ])->all(),
            'emergency_contacts' => $medicalInfo->emergencyContacts->map(fn (EmergencyContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'relationship' => $contact->relationship,
                'phone_country_code' => $contact->phone_country_code,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary,
            ])->all(),
            'created_at' => $medicalInfo->created_at?->toDateString(),
            'updated_at' => $medicalInfo->updated_at?->toDateString(),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data): bool
    {
        $emailChanged = false;

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->accountService->updateEmail($user, $data['email'], 'dashboard');
            $emailChanged = true;
        }

        $medical = Arr::except($data, ['email']);

        $this->repository->upsertForUser($user, $medical);
        $user->update(['name' => trim("{$medical['first_name']} {$medical['last_name']}")]);
        $this->flushCache($user->id);

        return $emailChanged;
    }

    public function flushCache(int $userId): void
    {
        Cache::forget("user.{$userId}.dashboard");
    }
}
