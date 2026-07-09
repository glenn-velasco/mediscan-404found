<?php

namespace App\Repositories\Eloquent;

use App\Models\EmergencyContact;
use App\Models\MedicalInformation;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<EmergencyContact>
 */
class EmergencyContactRepository extends BaseRepository
{
    public function __construct(EmergencyContact $emergencyContact)
    {
        parent::__construct($emergencyContact);
    }

    /** @param  array<string, mixed>  $data */
    public function createForMedicalInformation(MedicalInformation $medicalInfo, array $data): EmergencyContact
    {
        return $medicalInfo->emergencyContacts()->create($data);
    }

    /**
     * @return LengthAwarePaginator<int, EmergencyContact>
     */
    public function findByMedicalInformation(MedicalInformation $medicalInfo, int $perPage = 15): LengthAwarePaginator
    {
        return $medicalInfo->emergencyContacts()->orderByDesc('is_primary')->orderByDesc('id')->paginate($perPage);
    }
}
