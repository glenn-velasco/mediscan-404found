<?php

namespace App\Services\Sync;

use App\Enums\AuditLogType;
use App\Enums\EnvelopeType;
use App\Events\PendingSyncEnvelopeCreated;
use App\Models\Allergy;
use App\Models\Condition;
use App\Models\Diagnosis;
use App\Models\Medication;
use App\Models\PendingSyncEnvelope;
use App\Models\User;
use App\Models\UserDeviceKey;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfessionalSyncService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Get the patient's current active device public key.
     * Returns null if no key is registered.
     */
    public function patientPublicKey(User $patient): ?string
    {
        $key = UserDeviceKey::where('user_id', $patient->id)
            ->where('is_active', true)
            ->latest('registered_at')
            ->first();

        return $key?->public_key;
    }

    /**
     * Submit an encrypted envelope for a patient.
     *
     * For verification envelope types, automatically updates the `verified_by`
     * JSON column on the target record (last write wins).
     */
    public function submitEnvelope(
        User $professional,
        User $patient,
        string $ciphertext,
        string $envelopeType,
        ?string $itemIdentifier = null,
        bool $verified = true,
    ): PendingSyncEnvelope {
        return DB::transaction(function () use ($professional, $patient, $ciphertext, $envelopeType, $itemIdentifier, $verified) {
            $envelope = PendingSyncEnvelope::create([
                'sender_id' => $professional->id,
                'recipient_id' => $patient->id,
                'envelope_type' => $envelopeType,
                'ciphertext' => $ciphertext,
                'expires_at' => now()->addDays(90),
            ]);

            // Auto-track verification on the record itself
            if ($itemIdentifier && $this->isVerificationType($envelopeType)) {
                $this->updateRecordVerification(
                    $professional,
                    $patient,
                    $envelopeType,
                    $itemIdentifier,
                    $verified,
                );
            }

            $this->auditLogger->log(
                action: 'envelope.submitted',
                type: AuditLogType::Create,
                actor: $professional,
                subject: $patient,
                metadata: [
                    'envelope_id' => $envelope->id,
                    'envelope_type' => $envelopeType,
                    'expires_at' => $envelope->expires_at,
                    'item_identifier' => $itemIdentifier,
                    'verified' => $verified,
                ],
                channel: 'api',
            );

            event(new PendingSyncEnvelopeCreated(
                envelopeId: $envelope->id,
                recipientId: $patient->id,
                envelopeType: $envelopeType,
            ));

            return $envelope;
        });
    }

    /**
     * Get verification status for a professional's view of a patient.
     *
     * @return array{allergies: list<array{item: string, verified: bool, verified_at: string|null}>, conditions: list<array{item: string, verified: bool, verified_at: string|null}>, diagnoses: list<array{item: string, verified: bool, verified_at: string|null}>, medications: list<array{item: string, verified: bool, verified_at: string|null}>}
     */
    public function getVerifications(User $professional, User $patient): array
    {
        $medicalInformationId = $patient->medical_information_id;

        if (! $medicalInformationId) {
            return [
                'allergies' => [],
                'conditions' => [],
                'diagnoses' => [],
                'medications' => [],
            ];
        }

        $allergies = Allergy::where('medical_information_id', $medicalInformationId)
            ->get()
            ->map(fn (Allergy $a) => [
                'item' => $a->allergen,
                'verified' => $a->isVerifiedBy($professional->id),
                'verified_at' => ($a->getVerificationFor($professional->id) ?? [])['verified_at'] ?? null,
            ])
            ->filter(fn ($v) => $v['verified'] || $v['verified_at'] !== null)
            ->values()
            ->toArray();

        $conditions = Condition::where('medical_information_id', $medicalInformationId)
            ->get()
            ->map(fn (Condition $c) => [
                'item' => $c->description,
                'verified' => $c->isVerifiedBy($professional->id),
                'verified_at' => ($c->getVerificationFor($professional->id) ?? [])['verified_at'] ?? null,
            ])
            ->filter(fn ($v) => $v['verified'] || $v['verified_at'] !== null)
            ->values()
            ->toArray();

        $diagnoses = Diagnosis::where('medical_information_id', $medicalInformationId)
            ->get()
            ->map(fn (Diagnosis $d) => [
                'item' => $d->condition,
                'verified' => $d->isVerifiedBy($professional->id),
                'verified_at' => ($d->getVerificationFor($professional->id) ?? [])['verified_at'] ?? null,
            ])
            ->filter(fn ($v) => $v['verified'] || $v['verified_at'] !== null)
            ->values()
            ->toArray();

        $medications = Medication::where('medical_information_id', $medicalInformationId)
            ->get()
            ->map(fn (Medication $m) => [
                'item' => $m->name,
                'verified' => $m->isVerifiedBy($professional->id),
                'verified_at' => ($m->getVerificationFor($professional->id) ?? [])['verified_at'] ?? null,
            ])
            ->filter(fn ($v) => $v['verified'] || $v['verified_at'] !== null)
            ->values()
            ->toArray();

        // @phpstan-ignore-next-line toArray() loses generic types but shape is correct per @return annotation
        return [
            'allergies' => $allergies,
            'conditions' => $conditions,
            'diagnoses' => $diagnoses,
            'medications' => $medications,
        ];
    }

    /**
     * Update verification status on the actual record.
     * If the record doesn't exist yet, create it first.
     */
    private function updateRecordVerification(
        User $professional,
        User $patient,
        string $envelopeType,
        string $itemIdentifier,
        bool $verified,
    ): void {
        $medicalInformationId = $patient->medical_information_id;

        if (! $medicalInformationId) {
            return;
        }

        $record = $this->findRecord($envelopeType, $medicalInformationId, $itemIdentifier);

        if (! $record) {
            $record = $this->createRecordIfMissing($envelopeType, $medicalInformationId, $itemIdentifier, $professional);
        }

        if ($record && method_exists($record, 'toggleVerification')) {
            $newVerifiedBy = $record->toggleVerification($professional, $verified);
            $record->update(['verified_by' => $newVerifiedBy]);
        }
    }

    /**
     * Create a record if it doesn't exist on the server yet.
     * This handles the case where a professional verifies an item via BLE
     * before the patient's sync has created the record.
     */
    private function createRecordIfMissing(
        string $envelopeType,
        int $medicalInformationId,
        string $itemIdentifier,
        User $professional,
    ): ?Model {
        $modelClass = match ($envelopeType) {
            EnvelopeType::AllergyVerification->value => Allergy::class,
            EnvelopeType::ConditionVerification->value => Condition::class,
            EnvelopeType::DiagnosisVerification->value => Diagnosis::class,
            EnvelopeType::MedicationVerification->value => Medication::class,
            default => null,
        };

        if (! $modelClass) {
            return null;
        }

        // Check again inside the transaction to avoid race conditions
        return DB::transaction(function () use ($modelClass, $envelopeType, $medicalInformationId, $itemIdentifier, $professional) {
            $existing = $this->findRecord($envelopeType, $medicalInformationId, $itemIdentifier);
            if ($existing) {
                return $existing;
            }

            $data = match ($envelopeType) {
                EnvelopeType::AllergyVerification->value => [
                    'id' => Str::uuid(),
                    'medical_information_id' => $medicalInformationId,
                    'allergen' => $itemIdentifier,
                    'severity' => 'mild',
                ],
                EnvelopeType::ConditionVerification->value => [
                    'id' => Str::uuid(),
                    'medical_information_id' => $medicalInformationId,
                    'description' => $itemIdentifier,
                ],
                EnvelopeType::DiagnosisVerification->value => [
                    'id' => Str::uuid(),
                    'medical_information_id' => $medicalInformationId,
                    'condition' => $itemIdentifier,
                    'diagnosed_by' => $professional->id,
                    'severity' => 'chronic',
                ],
                EnvelopeType::MedicationVerification->value => [
                    'id' => Str::uuid(),
                    'medical_information_id' => $medicalInformationId,
                    'name' => $itemIdentifier,
                    'dosage' => '',
                    'frequency' => '',
                ],
                default => null,
            };

            if (! $data) {
                return null;
            }

            $record = $modelClass::create($data);

            $this->auditLogger->log(
                action: strtolower(class_basename($modelClass)).'.created_by_verification',
                type: AuditLogType::Create,
                actor: $professional,
                subject: $professional,
                metadata: ['item_identifier' => $itemIdentifier],
                channel: 'api',
            );

            return $record;
        });
    }

    /**
     * Track a verification directly (without creating an envelope).
     *
     * Used when a professional toggles verification locally and syncs to the server.
     */
    public function trackVerification(
        User $professional,
        User $patient,
        string $envelopeType,
        string $itemIdentifier,
        bool $verified,
    ): void {
        $this->updateRecordVerification(
            $professional,
            $patient,
            $envelopeType,
            $itemIdentifier,
            $verified,
        );
    }

    /**
     * Find a record by type and item identifier.
     */
    private function findRecord(string $envelopeType, int $medicalInformationId, string $itemIdentifier): ?Model
    {
        return match ($envelopeType) {
            EnvelopeType::AllergyVerification->value => Allergy::where('medical_information_id', $medicalInformationId)
                ->where('allergen', $itemIdentifier)
                ->first(),
            EnvelopeType::ConditionVerification->value => Condition::where('medical_information_id', $medicalInformationId)
                ->where('description', $itemIdentifier)
                ->first(),
            EnvelopeType::DiagnosisVerification->value => Diagnosis::where('medical_information_id', $medicalInformationId)
                ->where('condition', $itemIdentifier)
                ->first(),
            EnvelopeType::MedicationVerification->value => Medication::where('medical_information_id', $medicalInformationId)
                ->where('name', $itemIdentifier)
                ->first(),
            default => null,
        };
    }

    /**
     * Check if envelope type is a verification type.
     */
    private function isVerificationType(string $envelopeType): bool
    {
        return in_array($envelopeType, [
            EnvelopeType::AllergyVerification->value,
            EnvelopeType::ConditionVerification->value,
            EnvelopeType::DiagnosisVerification->value,
            EnvelopeType::MedicationVerification->value,
        ]);
    }
}
