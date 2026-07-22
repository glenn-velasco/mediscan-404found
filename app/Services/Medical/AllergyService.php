<?php

namespace App\Services\Medical;

use App\Models\Allergy;

class AllergyService extends PatientRecordService
{
    protected function modelClass(): string
    {
        return Allergy::class;
    }

    protected function recordType(): string
    {
        return 'allergy';
    }
}
