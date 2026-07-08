<?php

namespace App\Services\Professional;

use App\Enums\Permission;
use App\Events\AllergyVerified;
use App\Events\TransfusionConsentUpdated;
use App\Models\Allergy;
use App\Models\MedicalInformation;
use App\Models\User;
use App\Repositories\Eloquent\MedicalInformationRepository;
use App\Services\User\MedicalInfoService;

class PatientVerificationService
{
    public function __construct(
        private MedicalInfoService $medicalInfoService,
        private MedicalInformationRepository $medicalInfoRepository,
    ) {}

    /**
     * Exact-email lookup only, and the same 404 whether the user does not
     * exist or has no medical information, to limit email enumeration.
     */
    public function findPatientByEmail(string $email): User
    {
        $patient = User::query()->where('email', $email)->first();

        abort_if(! $patient || ! $patient->medicalInformation()->exists(), 404);

        return $patient;
    }

    /** @return array<string, mixed> */
    public function patientView(User $patient): array
    {
        $view = $this->medicalInfoService->show($patient);

        abort_if($view === null, 404);

        return [
            'id' => $patient->id,
            'email' => $patient->email,
            ...$view,
        ];
    }

    public function patientMedicalInformation(User $professional, User $patient): MedicalInformation
    {
        $this->authorizeProfessional($professional, $patient->id);

        $medicalInfo = $this->medicalInfoRepository->findWithRelationsByUser($patient);

        abort_if(! $medicalInfo, 404);

        return $medicalInfo;
    }

    public function verifyAllergy(User $professional, Allergy $allergy): Allergy
    {
        $patientUserId = $allergy->medicalInformation()->firstOrFail()->user_id;

        $this->authorizeProfessional($professional, $patientUserId);

        $allergy->forceFill([
            'verified_by' => $professional->id,
            'verified_at' => now(),
        ])->save();

        $this->medicalInfoService->flushCache($patientUserId);

        AllergyVerified::dispatch($patientUserId, $allergy->id);

        return $allergy;
    }

    public function witnessTransfusionDecision(User $professional, MedicalInformation $medicalInfo): MedicalInformation
    {
        $this->authorizeProfessional($professional, $medicalInfo->user_id);

        // A witness attests a recorded decision; nothing to attest until the
        // patient has saved their transfusion choice at least once.
        abort_if($medicalInfo->transfusion_decision_at === null, 422, 'The patient has not recorded a transfusion decision yet.');

        $witnesses = $medicalInfo->transfusion_witnesses ?? [];

        abort_if(
            collect($witnesses)->contains(fn (array $witness) => $witness['user_id'] === $professional->id),
            422,
            'You have already witnessed this decision.',
        );

        // The name is a snapshot taken at witnessing time so the attestation
        // survives later account changes or deletion.
        $witnesses[] = [
            'user_id' => $professional->id,
            'name' => $professional->name,
            'witnessed_at' => now()->toIso8601String(),
        ];

        $medicalInfo->forceFill(['transfusion_witnesses' => $witnesses])->save();

        $this->medicalInfoService->flushCache($medicalInfo->user_id);

        TransfusionConsentUpdated::dispatch(
            $medicalInfo->user_id,
            $medicalInfo->no_blood_transfusion,
            count($witnesses),
        );

        return $medicalInfo;
    }

    private function authorizeProfessional(User $professional, int $patientUserId): void
    {
        abort_unless($professional->hasPermissionTo(Permission::VerifiedProfessional->value), 403);

        abort_if($professional->id === $patientUserId, 403, 'Professionals cannot attest their own records.');
    }
}
