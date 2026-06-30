<?php

namespace App\Repositories\Eloquent;

use App\Models\EmergencyContact;
use App\Models\MedicalInformation;

class EmergencyContactRepository extends BaseRepository
{
    public function __construct(EmergencyContact $emergencyContact)
    {
        parent::__construct($emergencyContact);
    }

    public function createForMedicalInformation(MedicalInformation $medicalInfo, array $data): EmergencyContact
    {
        return $medicalInfo->emergencyContacts()->create($data);
    }
}
