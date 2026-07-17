<?php

namespace App\Contracts\Repositories;

use App\Models\MedicalInformation;

/**
 * @extends BaseRepositoryContract<MedicalInformation>
 */
interface MedicalInformationRepositoryContract extends BaseRepositoryContract
{
    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function findMatchingByName(array $nameFields, string $dob): ?MedicalInformation;
}
