# APP_KEY rotation runbook

Not covered by Scribe (no HTTP routes) or `docs/BACKUPS.md` (that document covers the *separate* backup-encryption key, deliberately not this one — see "Why a separate key" below). This is the runbook for rotating `APP_KEY`, the key behind every `encrypted`/`encrypted:array` Eloquent cast in this app — `MedicalInformation`'s PII fields, `Allergy`/`Diagnosis`/`Medication`/`EmergencyContact`'s PHI fields, and `ProfessionalApplication`'s OCR/license data (see `docs/ARCHITECTURE.md` and each model's `casts()` for the full list).

## Why a separate key from the backup key

`APP_KEY` protects live PHI at rest in the running database. The backup GPG key (`docs/BACKUPS.md`) protects historical dump files. They're deliberately independent — a leaked `APP_KEY` shouldn't also compromise every past backup, and vice versa. This runbook is only about `APP_KEY`.

## Why this isn't automated

Rotating `APP_KEY` means re-encrypting every existing PHI value with a new key while the app keeps running — this touches live production data across every table with an `encrypted` cast, and a mistake here (wrong key, interrupted job, wrong environment) is a real data-loss risk. That's not something to script casually for a solo-dev app; it's an infrequent (annual-ish), deliberate, supervised operation. Laravel does provide the mechanism to do it safely (`APP_PREVIOUS_KEYS`, already wired up in `config/app.php:102-106`) — this document is the manual procedure for using it.

## When to rotate

- Routinely: every 6-12 months as basic hygiene.
- Immediately: if `APP_KEY` (or anything that could reveal it — a `.env` file, a GitHub Environment secret, a VPS backup that wasn't supposed to include it) is suspected leaked.

## Procedure

1. **Generate the new key** without applying it yet:
   ```sh
   docker compose exec app php artisan key:generate --show
   ```
   This prints a new `base64:...` key — copy it, don't apply it via `key:generate` directly (that would overwrite `APP_KEY` immediately, before the re-encryption pass, corrupting every currently-encrypted value that hasn't been rewritten yet).

2. **Stage the rotation**: set the *current* production `APP_KEY` as `APP_PREVIOUS_KEYS`, and the newly generated key as `APP_KEY`, in the `production` GitHub Environment (`docs/DEPLOYMENT_SETUP.md` §3.4):
   ```
   APP_PREVIOUS_KEYS=<the key that was just replaced>
   APP_KEY=<the newly generated key>
   ```
   With both set, Laravel's `Encrypter` decrypts using either key (trying `APP_KEY` first, falling back to each of `APP_PREVIOUS_KEYS`) but only ever *encrypts* new writes with the current `APP_KEY`. This means the app keeps working correctly the moment this deploys — old rows decrypt via the previous key, new writes use the new one — nothing is broken mid-rotation, which is what makes the next step safe to run without downtime.

3. **Deploy** this config change (`docs/DEPLOYMENT_SETUP.md` §6.1) and confirm the app is healthy — existing PHI should still read correctly (it's being decrypted via `APP_PREVIOUS_KEYS` at this point), and any new record created now should encrypt with the new key.

4. **Re-encrypt every existing row under the new key.** For each model with an `encrypted`/`encrypted:array` cast (`MedicalInformation`, `Allergy`, `Diagnosis`, `Medication`, `EmergencyContact`, `ProfessionalApplication`), re-saving the model is enough to make Eloquent decrypt-then-re-encrypt every cast field under the current key. Run via `php artisan tinker` on the deployed box (`docker compose exec app php artisan tinker`), one model at a time, in small chunks so a large table doesn't hold a long-running transaction or exhaust memory:
   ```php
   App\Models\MedicalInformation::query()->chunkById(200, function ($rows) {
       foreach ($rows as $row) {
           $row->save(); // no fields changed - just forces re-encrypt of every cast column
       }
   });
   ```
   Repeat for `Allergy`, `Diagnosis`, `Medication`, `EmergencyContact`, `ProfessionalApplication`. This is safe to interrupt and re-run — re-saving an already-rotated row just re-encrypts it again with the same (current) key, a harmless no-op in effect.

5. **Verify** a sample of rows read back correctly (spot check via tinker, or the admin UI) before removing the old key.

6. **Remove `APP_PREVIOUS_KEYS`** once confident every row has been re-saved (step 4 fully completed without errors). Deploy that change. From this point, the old key can no longer decrypt anything in this database — losing/leaking it afterward is no longer a PHI exposure risk.

## If a re-encryption pass is interrupted partway

Not a problem — leave `APP_PREVIOUS_KEYS` in place and re-run step 4. Since re-saving is idempotent (rows already on the new key just get re-encrypted with the same key again), there's no harm in running the chunked re-save again from the start; it doesn't need to track where it left off.
