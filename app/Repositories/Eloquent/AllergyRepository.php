<?php

namespace App\Repositories\Eloquent;

use App\Models\Allergy;
use App\Models\MedicalInformation;

class AllergyRepository extends BaseRepository
{
    public function __construct(Allergy $allergy)
    {
        parent::__construct($allergy);
    }

    public function createForMedicalInformation(MedicalInformation $medicalInfo, array $data): Allergy
    {
        return $medicalInfo->allergies()->create($data);
    }
}
