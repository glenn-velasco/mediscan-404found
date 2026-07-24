<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Models\User;

class DiagnosisPolicy
{
    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $diagnosis->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    /**
     * A verified professional linked to the target record may author a diagnosis,
     * or a patient may upload a diagnosis received from a professional (e.g. via BLE)
     * as long as they own the medical information record.
     */
    public function create(User $user, MedicalInformation $medicalInformation): bool
    {
        $ownsRecord = $medicalInformation->users()->whereKey($user->id)->exists();

        // Patient uploading a BLE-received diagnosis from a verified professional
        if ($ownsRecord && ! $user->can(Permission::VerifiedProfessional->value)) {
            return true;
        }

        // Verified professional authoring a new diagnosis
        return $user->can(Permission::VerifiedProfessional->value) && $ownsRecord;
    }

    public function update(User $user, Diagnosis $diagnosis): bool
    {
        // Patient can update diagnoses they uploaded (owns the medical information record)
        if ($this->view($user, $diagnosis) && ! $user->can(Permission::VerifiedProfessional->value)) {
            return true;
        }

        // Verified professional can update any diagnosis on a linked record
        return $user->can(Permission::VerifiedProfessional->value) && $this->view($user, $diagnosis);
    }

    public function delete(User $user, Diagnosis $diagnosis): bool
    {
        return $this->update($user, $diagnosis);
    }
}
