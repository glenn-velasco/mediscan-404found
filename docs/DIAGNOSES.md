# Conditions vs. diagnoses, and why diagnosis authorship is gated

Scribe documents the individual `diagnoses` endpoints' request/response shape but not *why* diagnoses are authorized differently from the other three patient-authored resources (allergies, medications, emergency contacts). This document is the source of truth for that distinction. See `docs/SYNC.md` for the shared patient-authored CRUD/sync architecture that diagnoses otherwise follow.

## Condition vs. diagnosis

- A **condition** is the general state of a person's health — a disease, disorder, or injury.
- A **diagnosis** is the *formal, professional* identification of a specific condition, reached by a healthcare provider through examination, symptom evaluation, and testing.

These are modeled as two separate tables: `Condition`/`conditions` (a single free-text `description` field — deliberately *not* an enum/fixed list, since a patient's own account of their health shouldn't be constrained to a predefined set of terms) and `Diagnosis`/`diagnoses` (`condition`, `date_of_diagnosis`, `severity`). `Condition` is patient-authored, exactly like allergies/medications/emergency contacts (any user linked to the record can create/update/delete their own); `Diagnosis` is the one exception, gated to verified professionals via **authorship**, described below. A patient can still view diagnoses on their own record; they just can't author them, the same way a real patient can't write their own formal diagnosis into a medical chart.

`Condition` and `Diagnosis` aren't linked to each other in the schema (no FK between them) — a professional reads a patient's self-reported conditions the same way they'd read any other patient-authored context, and records their own formal diagnosis as a separate row.

## Authorization

`App\Policies\DiagnosisPolicy`:
- `view`: any user linked to the diagnosis's `medical_information` record (via the `users` pivot) — unchanged from the other three resources.
- `create`: the actor must hold the `App\Enums\Permission::VerifiedProfessional` permission **and** be linked to the target `MedicalInformation` record. Being a verified professional alone isn't enough — they must be one of the users associated with that specific patient's record (family/account link), not any patient globally.
- `update`/`delete`: same as `create`, checked against the diagnosis's own record.

`VerifiedProfessional` is granted by the existing KYC pipeline (`App\Services\Kyc\ProfessionalApplicationService::approve()`), which creates a profession-named Spatie role (e.g. `doctor`) and gives it the `verified professional` permission, then assigns it to the approved user. Nothing new was added to the KYC flow — this only wires an existing permission into `DiagnosisPolicy`, which previously ignored roles/permissions entirely (ownership-only, like its siblings).

## Route shape

Unlike allergies/medications/emergency contacts, `POST` to create a diagnosis is **not** `POST /api/v1/diagnoses` — it's:

```
POST /api/v1/medical-information/{medicalInformation}/diagnoses
```

The other three resources always create on the *actor's own* linked record (`$actor->medical_information_id`), so no explicit target is needed. A diagnosis is usually authored by a professional acting on *someone else's* record, so the target `MedicalInformation` must be explicit in the route rather than implicit from the actor. `GET/PUT/DELETE /api/v1/diagnoses/{diagnosis}` are unchanged (the diagnosis ID alone identifies its own record).

## Authorship tracking: `diagnosed_by`

`diagnoses.diagnosed_by` (nullable FK to `users`, `nullOnDelete`) records which professional authored the diagnosis, set from the authenticated actor at creation time — never client-supplied. Exposed on `DiagnosisResource` as:

```json
"diagnosed_by": { "id": 1, "fullname": "Dr. Jane Doe" }
```

This is a distinct concept from the existing **verification** system (`professional_attestations` on the mobile client, surfaced via `VerifiedCountBadge`) — attestation is a post-hoc "a professional confirmed this is accurate" signal that can apply to any patient-authored record, while `diagnosed_by` is *authorship*, specific to diagnoses, and set once at creation.
