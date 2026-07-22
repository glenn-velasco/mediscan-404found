<?php

namespace App\Services\Medical;

use App\Models\Allergy;
use App\Models\Diagnosis;
use App\Models\EmergencyContact;
use App\Models\MedicalInformation;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Backs `GET /sync?since=` - the mobile app's bulk pull for everything
 * changed (created/updated/soft-deleted) since its last successful sync, across
 * all patient-authored resources. See docs/SYNC.md.
 */
class SyncService
{
    /**
     * @return array<string, mixed>
     */
    public function pull(User $user, ?Carbon $since): array
    {
        $medicalInformationId = $user->medical_information_id;

        if ($medicalInformationId === null) {
            return [
                'server_time' => now()->toIso8601String(),
                'medical_information' => null,
                'allergies' => [],
                'diagnoses' => [],
                'medications' => [],
                'emergency_contacts' => [],
            ];
        }

        return [
            'server_time' => now()->toIso8601String(),
            'medical_information' => $this->pullMedicalInformation($medicalInformationId, $since),
            'allergies' => $this->pullResource(Allergy::class, $medicalInformationId, $since),
            'diagnoses' => $this->pullResource(Diagnosis::class, $medicalInformationId, $since),
            'medications' => $this->pullResource(Medication::class, $medicalInformationId, $since),
            'emergency_contacts' => $this->pullResource(EmergencyContact::class, $medicalInformationId, $since),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pullMedicalInformation(int $medicalInformationId, ?Carbon $since): ?array
    {
        $medicalInformation = MedicalInformation::query()->find($medicalInformationId);

        if ($medicalInformation === null) {
            return null;
        }

        if ($since !== null && $medicalInformation->updated_at?->lte($since)) {
            return null;
        }

        return [
            'id' => $medicalInformation->id,
            'first_name' => $medicalInformation->first_name,
            'middle_name' => $medicalInformation->middle_name,
            'last_name' => $medicalInformation->last_name,
            'suffix' => $medicalInformation->suffix,
            'dob' => $medicalInformation->dob->toDateString(),
            'gender' => $medicalInformation->gender->value,
            'blood_type' => $medicalInformation->blood_type?->value,
            'religion' => $medicalInformation->religion?->value,
            'national_id' => $medicalInformation->national_id,
            'address' => $medicalInformation->address,
            'no_blood_transfusion' => $medicalInformation->no_blood_transfusion,
            'updated_at' => $medicalInformation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  class-string<Allergy|Diagnosis|Medication|EmergencyContact>  $modelClass
     * @return array<int, array<string, mixed>>
     */
    private function pullResource(string $modelClass, int $medicalInformationId, ?Carbon $since): array
    {
        $query = $modelClass::withTrashed()->where('medical_information_id', $medicalInformationId);

        if ($since !== null) {
            $query->where(function ($q) use ($since) {
                $q->where('updated_at', '>', $since)->orWhere('deleted_at', '>', $since);
            });
        }

        return $query->get()->map(fn ($record) => [
            ...$record->toArray(),
            'deleted_at' => $record->deleted_at?->toIso8601String(),
        ])->all();
    }
}
