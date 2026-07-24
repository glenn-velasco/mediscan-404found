<?php

namespace App\Services\Medical;

use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

    protected function eagerLoad(): array
    {
        return ['diagnosedBy'];
    }

    /**
     * Diagnoses are authored by a verified professional on a patient's
     * record, not on the actor's own record - so this doesn't use the base
     * "actor's own medical_information_id" create() path.
     *
     * When a patient uploads a BLE-received diagnosis, diagnosed_by is taken
     * from the request (the professional who authored it). Otherwise it
     * defaults to the acting user.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForRecord(array $data, MedicalInformation $medicalInformation, User $actor): Model
    {
        $diagnosedById = $data['diagnosed_by']['id'] ?? $actor->id;

        // Strip nested diagnosed_by array — Eloquent expects the integer FK
        unset($data['diagnosed_by']);

        $record = Diagnosis::query()->create([
            ...$data,
            'medical_information_id' => $medicalInformation->id,
            'diagnosed_by' => $diagnosedById,
        ]);

        $this->recordCreatedAuditAndBroadcast($record, $actor, array_keys($data));

        return $record->load('diagnosedBy');
    }
}
