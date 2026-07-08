<?php

namespace App\Services\User;

use App\Models\Allergy;
use App\Models\User;
use App\Repositories\Eloquent\AllergyRepository;
use App\Repositories\Eloquent\MedicalInformationRepository;

class AllergyService
{
    public function __construct(
        private AllergyRepository $repository,
        private MedicalInformationRepository $medicalInfoRepository,
        private MedicalInfoService $medicalInfoService,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(User $user, array $data): Allergy
    {
        $medicalInfo = $this->medicalInfoRepository->findByUser($user);

        abort_if(! $medicalInfo, 404);

        $allergy = $this->repository->createForMedicalInformation($medicalInfo, $data);

        $this->medicalInfoService->flushCache($user->id);

        return $allergy;
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, Allergy $allergy, array $data): bool
    {
        $this->authorizeOwnership($user, $allergy);

        // A professional attested a specific allergen/reaction/severity;
        // changing any of them voids that attestation.
        if ($allergy->isVerified() && $this->substanceChanged($allergy, $data)) {
            $data['verified_by'] = null;
            $data['verified_at'] = null;
        }

        $result = $this->repository->update($allergy, $data);

        $this->medicalInfoService->flushCache($user->id);

        return $result;
    }

    public function delete(User $user, Allergy $allergy): bool
    {
        $this->authorizeOwnership($user, $allergy);

        $result = $this->repository->delete($allergy);

        $this->medicalInfoService->flushCache($user->id);

        return $result;
    }

    private function authorizeOwnership(User $user, Allergy $allergy): void
    {
        abort_unless($allergy->medical_information_id === $user->medicalInformation?->id, 404);
    }

    /** @param  array<string, mixed>  $data */
    private function substanceChanged(Allergy $allergy, array $data): bool
    {
        return collect(['allergen', 'reaction', 'severity'])->contains(function (string $field) use ($allergy, $data) {
            if (! array_key_exists($field, $data)) {
                return false;
            }

            $current = $allergy->{$field};

            return $data[$field] !== ($current instanceof \BackedEnum ? $current->value : $current);
        });
    }
}
