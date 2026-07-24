<?php

namespace App\Services\Medical;

use App\Models\Condition;

class ConditionService extends PatientRecordService
{
    protected function modelClass(): string
    {
        return Condition::class;
    }

    protected function recordType(): string
    {
        return 'condition';
    }
}
