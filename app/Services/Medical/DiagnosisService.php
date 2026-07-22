<?php

namespace App\Services\Medical;

use App\Models\Diagnosis;

class DiagnosisService extends PatientRecordService
{
    protected function modelClass(): string
    {
        return Diagnosis::class;
    }

    protected function recordType(): string
    {
        return 'diagnosis';
    }
}
