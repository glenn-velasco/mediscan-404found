# Patient data sync

Not covered by Scribe for the architectural "why" (Scribe documents the individual HTTP endpoints — `GET/POST/PUT/DELETE api/v1/allergies` etc. — but not how they fit together as a sync system) and not covered by `docs/BROADCASTING.md` beyond the one `PatientRecordUpdated` event row. This document is the source of truth for the patient-authored CRUD + sync architecture: what it is, why it's separate from the BLE/envelope system, and how conflicts/deletes/offline-created records are handled.

## Two independent data flows — don't conflate them

This app has **two separate sync systems** that happen to touch overlapping-sounding data. Keeping them mentally separate matters:

1. **Professional attestation (BLE / sealed-box / envelope)** — a clinician's device pushes signed verification data (allergy/diagnosis/medication verifications, transfusion witness records) to a patient's device, offline, over BLE. Backed by `device_keys`, `pending_sync_envelopes`, `professional_attestations` (mobile) and the `Device Keys`/`Sync`/`Professional` route groups + `PendingSyncService`/`ProfessionalSyncService` (server). **Unchanged by this document** — still exactly what it was.
2. **Patient-authored CRUD sync (this document)** — the patient's own edits to their allergies, diagnoses, medications, emergency contacts, and medical information, synced directly between their phone and the cloud API over plain HTTPS. No BLE, no envelopes, no device keys involved.

A patient adding "Peanuts" to their own allergy list never touches the BLE/envelope path at all. A clinician verifying that allergy during a visit does — that's flow 1, and it's a separate write path (`professional_attestations`, matched against a patient's records after the fact) that this document doesn't change.

## Why client-generated UUIDs, not server-assigned IDs

The phone can create records fully offline (adding an allergy on a plane, with no connectivity). At that moment there's no server row yet — the phone needs *some* identity for that record before the server has ever seen it.

The chosen design: **the client generates a UUID at creation time, and that UUID becomes the record's real primary key everywhere** — not a separate `local_uuid` tracking column bolted onto a server auto-increment `id`. Concretely, `allergies.id`/`diagnoses.id`/`medications.id`/`emergency_contacts.id` are `uuid` primary keys (see e.g. `database/migrations/2026_07_22_120000_create_allergies_table.php`), and the corresponding `App\Http\Requests\Store*Request` classes require the client to submit `id` as a UUID.

This was a deliberate simplification over the alternative (server always owns the ID; client creates a row under a temporary placeholder, then swaps to the server-assigned ID after first sync) — that alternative is also standard REST, but requires a client-side "key swap" step after every create, which is exactly the kind of bookkeeping that made the old BLE/envelope local-tracking design hard to follow. One ID, invented on the phone, used everywhere forever means the sync flow is: `POST /allergies` with the id already in the body, then plain `GET/PUT/DELETE /allergies/{id}` from then on — no reconciliation step.

`medical_information` itself keeps its existing server-assigned integer `id` (unchanged) — it's created through the existing registration flow, not offline-first the way these four child resources are.

## Server-side shape

- **Models**: `App\Models\Allergy`, `Diagnosis`, `Medication`, `EmergencyContact` — each `belongsTo(MedicalInformation::class)`, each with a UUID primary key (`public $incrementing = false; protected $keyType = 'string';`), each `use SoftDeletes`.
- **Encryption**: PHI free-text fields use Laravel's `encrypted` cast (`allergen`, `reaction`, `condition`, medication `name`/`dosage`/`notes`, contact `name`/`phone`) — same convention as `MedicalInformation` (see `docs/ARCHITECTURE.md` and the migration comments on each table for which fields are deliberately *not* encrypted, e.g. `severity` enums, and why).
- **Ownership**: `App\Policies\AllergyPolicy`/`DiagnosisPolicy`/`MedicationPolicy`/`EmergencyContactPolicy` — a user may act on a record only if they're one of the (possibly several) users linked to its parent `medical_information` row, mirroring `MedicalInformationPolicy`. Controllers return 404 (not 403) for a record that exists but isn't owned by the requester, matching the existing "never reveal a record exists to someone who doesn't own it" convention.
- **CRUD logic**: `App\Services\Medical\PatientRecordService` (abstract) holds the shared create/update/delete/list logic — audit logging and the `PatientRecordUpdated` broadcast are identical across all four resources, so that part is centralized; `AllergyService`/`DiagnosisService`/`MedicationService`/`EmergencyContactService` are thin subclasses naming their model and record-type label. Controllers stay one-per-resource (`AllergyController` etc.) rather than a shared generic controller — PHP's parameter-type variance rules don't allow a shared base controller to accept different `FormRequest` subtypes per resource cleanly, and one-controller-per-resource also matches this codebase's existing convention (`MedicalInformationController` has no shared base either).
- **Audit logging**: every create/update/delete calls `AuditLogger::log()` with `record: $model`, which populates the `audit_logs.record_type`/`record_id` columns (added in `database/migrations/2026_07_22_110000_add_record_to_audit_logs_table.php`). Update actions log `metadata: ['fields_changed' => [...]]` — field *names* only, never the PHI values, consistent with the existing "non-PHI context" convention documented on the `audit_logs` table itself.

## The pull endpoint: `GET /sync`

`App\Http\Controllers\Api\V1\SyncController` → `App\Services\Medical\SyncService::pull()`. Bulk-fetches everything changed (created, updated, or soft-deleted) since a given timestamp, across `medical_information` + all four child resources, scoped to the authenticated user's own linked record:

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
