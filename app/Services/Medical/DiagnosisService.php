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
     * @param  array<string, mixed>  $data
     */
    public function createForRecord(array $data, MedicalInformation $medicalInformation, User $actor): Model
    {
        $record = Diagnosis::query()->create([
            ...$data,
            'medical_information_id' => $medicalInformation->id,
            'diagnosed_by' => $actor->id,
        ]);

        $this->recordCreatedAuditAndBroadcast($record, $actor, array_keys($data));

        return $record->load('diagnosedBy');
    }
}
