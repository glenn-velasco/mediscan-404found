<?php

namespace App\Repositories\Eloquent;

use App\Models\EmergencyContact;
use App\Models\MedicalInformation;

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
}
