<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Events\PatientRecordUpdated;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared CRUD/audit/broadcast plumbing for the patient-authored resources
 * (Allergy, Condition, Medication, EmergencyContact - Diagnosis also extends
 * this for its shared list/audit/broadcast logic, but overrides create() to
 * require a verified professional; see docs/DIAGNOSES.md) - structurally
 * identical: each belongs to the actor's own medical_information record,
 * each gets the same audit-log shape, each fires the same
 * `PatientRecordUpdated` broadcast. See docs/SYNC.md.
 */
abstract class PatientRecordService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Short label used in audit actions and the broadcast payload, e.g. "allergy". */
    abstract protected function recordType(): string;

    /** @return array<int, string> Relations to eager-load when listing/returning records. */
    protected function eagerLoad(): array
    {
        return [];
    }

    /** @return Collection<int, Model> */
    public function listForUser(User $user): Collection
    {
        if ($user->medical_information_id === null) {
            return new Collection;
        }

        $modelClass = $this->modelClass();

        return $modelClass::query()
            ->where('medical_information_id', $user->medical_information_id)
            ->with($this->eagerLoad())
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Model
    {
        abort_unless($actor->medical_information_id !== null, 422, 'No medical information record linked to this account.');

        $modelClass = $this->modelClass();

        /** @var Model $record */
        $record = $modelClass::query()->create([
            ...$data,
            'medical_information_id' => $actor->medical_information_id,
        ]);

        $this->recordCreatedAuditAndBroadcast($record, $actor, array_keys($data));

        return $record;
    }

    protected function recordCreatedAuditAndBroadcast(Model $record, User $actor, array $fields): void
    {
        $this->auditLogger->log(
            action: "{$this->recordType()}.created",
            type: AuditLogType::Create,
            actor: $actor,
            subject: $actor,
            metadata: ['fields' => $fields],
            channel: 'api',
            record: $record,
        );

        $this->broadcast($record, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $record, array $data, User $actor): Model
    {
        $record->update($data);

        $this->auditLogger->log(
            action: "{$this->recordType()}.updated",
            type: AuditLogType::Update,
            actor: $actor,
            subject: $actor,
            metadata: ['fields_changed' => array_keys($record->getChanges())],
            channel: 'api',
            record: $record,
        );

        $this->broadcast($record, $actor);

        return $record->fresh() ?? $record;
    }

    public function delete(Model $record, User $actor): void
    {
        $recordId = (string) $record->getKey();

        $record->delete();

        $this->auditLogger->log(
            action: "{$this->recordType()}.deleted",
            type: AuditLogType::Delete,
            actor: $actor,
            subject: $actor,
            metadata: [],
            channel: 'api',
            record: $record,
        );

        PatientRecordUpdated::dispatch($this->recordType(), $recordId, $actor->id);
    }

    private function broadcast(Model $record, User $actor): void
    {
        PatientRecordUpdated::dispatch($this->recordType(), (string) $record->getKey(), $actor->id);
    }
}
