<?php

namespace App\Services\User;

use App\Events\EmergencyContactCreated;
use App\Events\EmergencyContactDeleted;
use App\Events\EmergencyContactUpdated;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Repositories\Eloquent\EmergencyContactRepository;
use App\Repositories\Eloquent\MedicalInformationRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmergencyContactService
{
    public function __construct(
        private EmergencyContactRepository $repository,
        private MedicalInformationRepository $medicalInfoRepository,
        private MedicalInfoService $medicalInfoService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, EmergencyContact>
     */
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $medicalInfo = $this->medicalInfoRepository->findByUser($user);

        abort_if(! $medicalInfo, 404);

        return $this->repository->findByMedicalInformation($medicalInfo, $perPage);
    }

    /** @param  array<string, mixed>  $data */
    public function create(User $user, array $data): EmergencyContact
    {
        $medicalInfo = $this->medicalInfoRepository->findByUser($user);

        abort_if(! $medicalInfo, 404);

        $contact = DB::transaction(function () use ($medicalInfo, $data) {
            if ($medicalInfo->emergencyContacts()->count() === 0) {
                $data['is_primary'] = true;
            } elseif (! empty($data['is_primary'])) {
                $medicalInfo->emergencyContacts()->update(['is_primary' => false]);
            } else {
                $data['is_primary'] = false;
            }

            return $this->repository->createForMedicalInformation($medicalInfo, $data);
        });

        $this->medicalInfoService->flushCache($user->id);

        EmergencyContactCreated::dispatch($user->id, $contact->id, $contact->name);

        return $contact;
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, EmergencyContact $contact, array $data): bool
    {
        $this->authorizeOwnership($user, $contact);

        $result = DB::transaction(function () use ($contact, $data) {
            if (! empty($data['is_primary'])) {
                $contact->medicalInformation->emergencyContacts()
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }

            return $this->repository->update($contact, $data);
        });

        $this->medicalInfoService->flushCache($user->id);

        EmergencyContactUpdated::dispatch($user->id, $contact->id, $contact->fresh()->name);

        return $result;
    }

    public function delete(User $user, EmergencyContact $contact): bool
    {
        $this->authorizeOwnership($user, $contact);

        $contactId = $contact->id;
        $result = $this->repository->delete($contact);

        $this->medicalInfoService->flushCache($user->id);

        EmergencyContactDeleted::dispatch($user->id, $contactId);

        return $result;
    }

    private function authorizeOwnership(User $user, EmergencyContact $contact): void
    {
        abort_unless($contact->medical_information_id === $user->medicalInformation?->id, 404);
    }
}
