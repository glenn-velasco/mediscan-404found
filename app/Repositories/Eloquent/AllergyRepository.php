<?php

namespace App\Repositories\Eloquent;

use App\Models\Allergy;
use App\Models\MedicalInformation;

/**
 * @extends BaseRepository<Allergy>
 */
class AllergyRepository extends BaseRepository
{
    public function __construct(Allergy $allergy)
    {
        parent::__construct($allergy);
    }

    /** @param  array<string, mixed>  $data */
    public function createForMedicalInformation(MedicalInformation $medicalInfo, array $data): Allergy
    {
        return $medicalInfo->allergies()->create($data);
    }
}
