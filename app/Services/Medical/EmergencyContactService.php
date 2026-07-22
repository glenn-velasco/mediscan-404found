<?php

namespace App\Services\Medical;

use App\Models\EmergencyContact;

class EmergencyContactService extends PatientRecordService
{
    protected function modelClass(): string
    {
        return EmergencyContact::class;
    }

    protected function recordType(): string
    {
        return 'emergency_contact';
    }
}
