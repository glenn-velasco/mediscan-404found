<?php

namespace App\Repositories\Eloquent;

use App\Models\Allergy;
use App\Models\MedicalInformation;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Allergy>
 */
class AllergyRepository extends BaseRepository
{
    public function __construct(Allergy $allergy)
    {
        parent::__construct($allergy);
    }

    /**
     * @return LengthAwarePaginator<int, Allergy>
     */
    public function paginateByMedicalInformation(MedicalInformation $medicalInfo, int $perPage = 15): LengthAwarePaginator
    {
        return $medicalInfo->allergies()->orderByDesc('id')->paginate($perPage);
    }

    /** @param  array<string, mixed>  $data */
    public function createForMedicalInformation(MedicalInformation $medicalInfo, array $data): Allergy
    {
        return $medicalInfo->allergies()->create($data);
    }
}
