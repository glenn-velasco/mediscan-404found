<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MedicalInformationRepositoryContract;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<MedicalInformation>
 */
class MedicalInformationRepository extends BaseRepository implements MedicalInformationRepositoryContract
{
    public function __construct(MedicalInformation $medicalInformation)
    {
        parent::__construct($medicalInformation);
    }

    /** @return MedicalInformation */
    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()
            ->with(['contacts', 'transfusionConsents'])
            ->findOrFail($id);
    }

    /**
     * Confident match: normalized name + exact dob. Returns null when there's
     * no match or more than one (ambiguous) - name+dob alone is not proof of
     * identity (twins, common names), so an ambiguous result must never be
     * treated as a match. See `MedicalInformationBuilder::matchingName()`.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function findMatchingByName(array $nameFields, string $dob): ?MedicalInformation
    {
        $matches = $this->model->newQuery()
            ->matchingName($nameFields, $dob)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
