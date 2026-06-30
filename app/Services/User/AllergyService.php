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

    public function create(User $user, array $data): Allergy
    {
        $medicalInfo = $this->medicalInfoRepository->findByUser($user);

        abort_if(! $medicalInfo, 404);

        $allergy = $this->repository->createForMedicalInformation($medicalInfo, $data);

        $this->medicalInfoService->flushCache($user->id);

        return $allergy;
    }

    public function update(User $user, Allergy $allergy, array $data): bool
    {
        $this->authorizeOwnership($user, $allergy);

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
}
