<?php

namespace App\Services\Medical;

use App\Models\Medication;

class MedicationService extends PatientRecordService
{
    protected function modelClass(): string
    {
        return Medication::class;
    }

    protected function recordType(): string
    {
        return 'medication';
    }
}
