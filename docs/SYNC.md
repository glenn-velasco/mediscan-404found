# Patient data sync

Not covered by Scribe for the architectural "why" (Scribe documents the individual HTTP endpoints — `GET/POST/PUT/DELETE api/v1/allergies` etc. — but not how they fit together as a sync system) and not covered by `docs/BROADCASTING.md` beyond the one `PatientRecordUpdated` event row. This document is the source of truth for the patient-authored CRUD + sync architecture: what it is, why it's separate from the BLE/envelope system, and how conflicts/deletes/offline-created records are handled.

## Two independent data flows — don't conflate them

This app has **two separate sync systems** that happen to touch overlapping-sounding data. Keeping them mentally separate matters:

1. **Professional attestation (BLE / sealed-box / envelope)** — a clinician's device pushes signed verification data (allergy/diagnosis/medication verifications, transfusion witness records) to a patient's device, offline, over BLE. Backed by `device_keys`, `pending_sync_envelopes`, `professional_attestations` (mobile) and the `Device Keys`/`Sync`/`Professional` route groups + `PendingSyncService`/`ProfessionalSyncService` (server). **Unchanged by this document** — still exactly what it was.
2. **Patient-authored CRUD sync (this document)** — the patient's own edits to their allergies, conditions, diagnoses, medications, emergency contacts, and medical information, synced directly between their phone and the cloud API over plain HTTPS. No BLE, no envelopes, no device keys involved.

A patient adding "Peanuts" to their own allergy list never touches the BLE/envelope path at all. A clinician verifying that allergy during a visit does — that's flow 1, and it's a separate write path (`professional_attestations`, matched against a patient's records after the fact) that this document doesn't change.

## Condition vs. diagnosis

- A **condition** is the general state of a person's health — a disease, disorder, or injury.
- A **diagnosis** is the *formal, professional* identification of a specific condition, reached by a healthcare provider through examination, symptom evaluation, and testing.

These are modeled as two separate tables: `Condition`/`conditions` (a single free-text `description` field — deliberately *not* an enum/fixed list, since a patient's own account of their health shouldn't be constrained to a predefined set of terms) and `Diagnosis`/`diagnoses` (`condition`, `date_of_diagnosis`, `severity`). `Condition` is patient-authored, exactly like allergies/medications/emergency contacts (any user linked to the record can create/update/delete their own); `Diagnosis` is the one exception, gated to verified professionals via **authorship**, described below. A patient can still view diagnoses on their own record; they just can't author them, the same way a real patient can't write their own formal diagnosis into a medical chart.

`Condition` and `Diagnosis` aren't linked to each other in the schema (no FK between them) — a professional reads a patient's self-reported conditions the same way they'd read any other patient-authored context, and records their own formal diagnosis as a separate row.

## Professional verification tracking

When a professional verifies a patient's allergy, condition, diagnosis, or medication, the verification status is tracked directly on the record via a `verified_by` JSON column. This applies equally to all four medical record types:

- `allergies.verified_by`
- `conditions.verified_by`
- `diagnoses.verified_by`
- `medications.verified_by`

This enables:

- **Correct toggle state**: The mobile app can correctly initialize the "Mark as verified" toggle based on whether the current professional has already verified each item.
- **Multiple professionals**: Each record can be verified by multiple professionals (idempotent per professional).
- **Offline support**: Verifications are queued locally and synced when online.

### How it works

1. **Envelope submission**: When a professional submits a verification envelope via `POST /professional/patients/{patient}/envelopes`, the server automatically updates the `verified_by` JSON column on the target record.

2. **Verification status**: `GET /professional/patients/{patient}/verifications` returns the current professional's verification status for each item type.

3. **Mobile offline queue**: The mobile app stores verification toggles in a local `pending_verification_syncs` table and syncs to the server when online.

### Data structure

The `verified_by` column stores an array of verification objects:

```json
[
  {"user_id": 1, "name": "Dr. Reyes", "verified_at": "2026-07-24T12:00:00Z"},
  {"user_id": 2, "name": "Dr. Santos", "verified_at": "2026-07-24T11:00:00Z"}
]
```

### Trait: `HasVerifications`

The `App\Models\Traits\HasVerifications` trait provides methods for working with verification data:

- `isVerifiedBy(int $userId)`: Check if a user has verified the record
- `getVerificationFor(int $userId)`: Get the verification entry for a specific user
- `getVerifiedCount()`: Get the count of professionals who verified the record
- `addVerification(User $professional)`: Add or update a verification
- `removeVerification(int $userId)`: Remove a verification
- `toggleVerification(User $professional, bool $verified)`: Toggle verification status

## Why client-generated UUIDs, not server-assigned IDs

The phone can create records fully offline (adding an allergy on a plane, with no connectivity). At that moment there's no server row yet — the phone needs *some* identity for that record before the server has ever seen it.

The chosen design: **the client generates a UUID at creation time, and that UUID becomes the record's real primary key everywhere** — not a separate `local_uuid` tracking column bolted onto a server auto-increment `id`. Concretely, `allergies.id`/`conditions.id`/`diagnoses.id`/`medications.id`/`emergency_contacts.id` are `uuid` primary keys (see e.g. `database/migrations/2026_07_22_120000_create_allergies_table.php`), and the corresponding `App\Http\Requests\Store*Request` classes require the client to submit `id` as a UUID.

This was a deliberate simplification over the alternative (server always owns the ID; client creates a row under a temporary placeholder, then swaps to the server-assigned ID after first sync) — that alternative is also standard REST, but requires a client-side "key swap" step after every create, which is exactly the kind of bookkeeping that made the old BLE/envelope local-tracking design hard to follow. One ID, invented on the phone, used everywhere forever means the sync flow is: `POST /allergies` with the id already in the body, then plain `GET/PUT/DELETE /allergies/{id}` from then on — no reconciliation step.

`medical_information` itself keeps its existing server-assigned integer `id` (unchanged) — it's created through the existing registration flow, not offline-first the way these five child resources are.

## Server-side shape

- **Models**: `App\Models\Allergy`, `Condition`, `Diagnosis`, `Medication`, `EmergencyContact` — each `belongsTo(MedicalInformation::class)`, each with a UUID primary key (`public $incrementing = false; protected $keyType = 'string';`), each `use SoftDeletes`.
- **Encryption**: PHI free-text fields use Laravel's `encrypted` cast (`allergen`, `reaction`, condition's `description`, diagnosis's `condition`, medication `name`/`dosage`/`notes`, contact `name`/`phone`) — same convention as `MedicalInformation` (see `docs/ARCHITECTURE.md` and the migration comments on each table for which fields are deliberately *not* encrypted, e.g. `severity` enums, and why).
- **Ownership**: `App\Policies\AllergyPolicy`/`ConditionPolicy`/`DiagnosisPolicy`/`MedicationPolicy`/`EmergencyContactPolicy` — a user may act on a record only if they're one of the (possibly several) users linked to its parent `medical_information` row, mirroring `MedicalInformationPolicy`. Controllers return 404 (not 403) for a record that exists but isn't owned by the requester, matching the existing "never reveal a record exists to someone who doesn't own it" convention. **Diagnoses are the one exception to "patient-authored"**: authoring a diagnosis additionally requires the `VerifiedProfessional` permission (see "Diagnosis authorization" below). Allergies, conditions, medications, and emergency contacts remain patient-self-authored.
- **CRUD logic**: `App\Services\Medical\PatientRecordService` (abstract) holds the shared create/update/delete/list logic — audit logging and the `PatientRecordUpdated` broadcast are identical across all five resources, so that part is centralized; `AllergyService`/`ConditionService`/`DiagnosisService`/`MedicationService`/`EmergencyContactService` are thin subclasses naming their model and record-type label. Controllers stay one-per-resource (`AllergyController` etc.) rather than a shared generic controller — PHP's parameter-type variance rules don't allow a shared base controller to accept different `FormRequest` subtypes per resource cleanly, and one-controller-per-resource also matches this codebase's existing convention (`MedicalInformationController` has no shared base either).
- **Audit logging**: every create/update/delete calls `AuditLogger::log()` with `record: $model`, which populates the `audit_logs.record_type`/`record_id` columns (added in `database/migrations/2026_07_22_110000_add_record_to_audit_logs_table.php`). Update actions log `metadata: ['fields_changed' => [...]]` — field *names* only, never the PHI values, consistent with the existing "non-PHI context" convention documented on the `audit_logs` table itself.

### Diagnosis authorization

`App\Policies\DiagnosisPolicy`:
- `view`: any user linked to the diagnosis's `medical_information` record (via the `users` pivot) — unchanged from the other four resources.
- `create`: the actor must hold the `App\Enums\Permission::VerifiedProfessional` permission **and** be linked to the target `MedicalInformation` record. Being a verified professional alone isn't enough — they must be one of the users associated with that specific patient's record (family/account link), not any patient globally.
- `update`/`delete`: same as `create`, checked against the diagnosis's own record.

`VerifiedProfessional` is granted by the existing KYC pipeline (`App\Services\Kyc\ProfessionalApplicationService::approve()`), which creates a profession-named Spatie role (e.g. `doctor`) and gives it the `verified professional` permission, then assigns it to the approved user. Nothing new was added to the KYC flow — this only wires an existing permission into `DiagnosisPolicy`, which previously ignored roles/permissions entirely (ownership-only, like its siblings).

### Diagnosis authorship tracking: `diagnosed_by`

`diagnoses.diagnosed_by` (nullable FK to `users`, `nullOnDelete`) records which professional authored the diagnosis, set from the authenticated actor at creation time — never client-supplied. Exposed on `DiagnosisResource` as:

```json
"diagnosed_by": { "id": 1, "fullname": "Dr. Jane Doe" }
```

This is a distinct concept from **verification** (`verified_by`) — authorship is specific to diagnoses and set once at creation, while verification is a post-hoc "a professional confirmed this is accurate" signal that applies equally to all medical record types.

### Diagnosis route shape

Unlike allergies/conditions/medications/emergency contacts, `POST` to create a diagnosis is **not** `POST /api/v1/diagnoses` — it's:

```
POST /api/v1/medical-information/{medicalInformation}/diagnoses
```

The other four resources always create on the *actor's own* linked record (`$actor->medical_information_id`), so no explicit target is needed. A diagnosis is usually authored by a professional acting on *someone else's* record, so the target `MedicalInformation` must be explicit in the route rather than implicit from the actor. `GET/PUT/DELETE /api/v1/diagnoses/{diagnosis}` are unchanged (the diagnosis ID alone identifies its own record).

## The pull endpoint: `GET /sync`

`App\Http\Controllers\Api\V1\SyncController` → `App\Services\Medical\SyncService::pull()`. Bulk-fetches everything changed (created, updated, or soft-deleted) since a given timestamp, across `medical_information` + all five child resources, scoped to the authenticated user's own linked record:

```
GET /api/v1/sync?since=2026-07-22T00:00:00Z
```

- Omit `since` to pull everything (first sync / fresh install).
- Each child-resource array uses `withTrashed()` and matches rows where `updated_at > since OR deleted_at > since` — soft-deleted rows are included (with `deleted_at` populated) specifically so the client can propagate the delete locally, not silently drop the row.
- `medical_information` is included only if it changed after `since` (or always, when `since` is omitted).
- Rate-limited via the `sync` limiter (`RateLimiter::for('sync', ...)` in `app/Providers/AppServiceProvider.php`, 30/min per user) — deliberately tighter than the general `api` limiter (120/min), since one call fetches everything rather than one record at a time.

## Push (client → server): plain CRUD, no bulk endpoint

There is no bulk push endpoint by design — the client pushes each locally-pending change individually via the normal REST routes (`POST`/`PUT`/`DELETE /allergies/{id}` etc.), driven by whatever local "this row hasn't been synced yet" bookkeeping the mobile app keeps (see the mobile repo's own docs for its `sync_status` column and `src/sync/triggers.ts` push loop). The server has no opinion on *how* the client decides what to push — it just accepts standard CRUD calls, each idempotent by virtue of the client-chosen UUID (retrying a `POST` for a UUID that already exists fails cleanly with a validation error, not a duplicate).

## Conflict resolution

Last-write-wins by `updated_at`, decided entirely on the mobile client during its pull-merge step — the server doesn't implement any conflict logic itself, it just always reflects the latest write it received. If two devices edit the same record while both offline, whichever device's `PUT` reaches the server *later* wins; the losing device finds out on its next `GET /sync` pull, when the server's `updated_at` is newer than what it expected.

## Realtime trigger

`PatientRecordUpdated` (see `docs/BROADCASTING.md`) fires on every create/update/delete, on the owning user's existing private `App.Models.User.{id}` channel (already wired up client-side via `BroadcastProvider`/`user-channel.ts`/`events/handlers.ts` for other events like `PendingSyncEnvelopeCreated`). The intent: the mobile client's `GET /sync` pull should be realtime-driven (react to this event) rather than relying solely on a fixed polling interval — the poll stays only as a fallback for missed events (app backgrounded, notification delivery gaps), not the primary trigger. The mobile-side wiring for this event doesn't exist yet — see the mobile repo's own sync docs for status.

## What's deliberately not built

- **No field-level search/filter on encrypted columns** at the database level (`ILIKE`, `ORDER BY` on `allergen` etc. won't match anything meaningful — Laravel's `encrypted` cast uses a random IV per save, so ciphertext for the same plaintext differs every time). Not needed today since every query here is already scoped to one user's small record set; see `docs/ARCHITECTURE.md` (or the `MedicalInformationRepository::findMatchingByName()` comment) for the general pattern this app uses instead (fetch the scoped rows, filter after Eloquent decrypts them).
- **No tiered backup retention specific to this data** — covered by the general database backup strategy in `docs/BACKUPS.md`, nothing sync-specific.
- **`medical_information.avatar` is not part of the sync payload.** It's set via its own dedicated endpoint (`POST /medical-information/{id}/avatar`, see the `Medical Information` group in Scribe) rather than through `PUT /medical-information/{id}` or `GET /sync` — a photo upload doesn't fit this document's plain-JSON CRUD model, and unlike the encrypted PHI fields here, the avatar is stored as a public URL, not sync state the client needs to reconcile offline. `App\Services\Medical\MedicalInformationService::syncAvatar()` fans the new photo out to every linked user's `profile_photo_path` on write, so no separate pull step is needed to propagate it to co-owners of the same record.
