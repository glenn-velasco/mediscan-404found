<?php

namespace App\Services\User;

use App\Events\TransfusionConsentUpdated;
use App\Models\Allergy;
use App\Models\EmergencyContact;
use App\Models\MedicalInformation;
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

        return Cache::remember(self::cacheKey($user->id), now()->addMonth(), fn () => [
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
            'transfusion_decision_at' => $medicalInfo->transfusion_decision_at?->toDateString(),
            'transfusion_witnesses' => $medicalInfo->transfusion_witnesses ?? [],
            'allergies' => $medicalInfo->allergies->map(fn (Allergy $allergy) => [
                'id' => $allergy->id,
                'allergen' => $allergy->allergen,
                'reaction' => $allergy->reaction,
                'severity' => $allergy->severity->value,
                'verified_at' => $allergy->verified_at?->toDateString(),
                'verified_by_name' => $allergy->verifier?->name,
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{medicalInfo: MedicalInformation, emailChanged: bool}
     */
    public function update(User $user, array $data, string $origin = 'dashboard'): array
    {
        $emailChanged = false;

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->accountService->updateEmail($user, $data['email'], $origin);
            $emailChanged = true;
        }

        $medical = Arr::except($data, ['email']);

        $previousFlag = $user->medicalInformation?->no_blood_transfusion;

        $medicalInfo = $this->repository->upsertForUser($user, $medical);

        if (array_key_exists('no_blood_transfusion', $medical)
            && (bool) $medical['no_blood_transfusion'] !== $previousFlag) {
            $this->stampTransfusionDecision($medicalInfo, $user);
        }

        $user->update(['name' => trim("{$medical['first_name']} {$medical['last_name']}")]);
        $this->flushCache($user->id);

        return [
            'medicalInfo' => $medicalInfo->fresh('allergies'),
            'emailChanged' => $emailChanged,
        ];
    }

    /**
     * Record the patient's explicit transfusion decision. Deliberately open
     * to any authenticated owner — no professional permission required.
     */
    public function recordTransfusionDecision(User $user, bool $noBloodTransfusion): MedicalInformation
    {
        $medicalInfo = $this->repository->findByUser($user);

        abort_if(! $medicalInfo, 404);

        $medicalInfo->no_blood_transfusion = $noBloodTransfusion;
        $this->stampTransfusionDecision($medicalInfo, $user);

        $this->flushCache($user->id);

        return $medicalInfo;
    }

    public function flushCache(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    /**
     * Versioned so a schema change in the cached payload (e.g. v2 replaced
     * the single witness columns with `transfusion_witnesses`) invalidates
     * month-old entries instead of serving a stale shape to the frontend.
     */
    public static function cacheKey(int $userId): string
    {
        return "user.{$userId}.dashboard.v2";
    }

    /**
     * Record who made the transfusion decision; witnesses attest a specific
     * decision, so changing it voids all attestations.
     */
    private function stampTransfusionDecision(MedicalInformation $medicalInfo, User $user): void
    {
        $medicalInfo->forceFill([
            'transfusion_decision_by' => $user->id,
            'transfusion_decision_at' => now(),
            'transfusion_witnesses' => [],
        ])->save();

        TransfusionConsentUpdated::dispatch(
            $user->id,
            $medicalInfo->no_blood_transfusion,
            0,
        );
    }
}
