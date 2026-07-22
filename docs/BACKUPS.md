# Database backups

Not covered by Scribe (no HTTP routes) or `BROADCASTING.md` (no broadcast events) — this document is the source of truth for how the production database is backed up, encrypted, and restored.

## Why this exists

The production database holds PHI (`medical_information` and related tables — see `docs/ARCHITECTURE.md`). Before this, there was no backup automation at all: a lost/corrupted database, a bad migration, or a compromised VPS meant permanent data loss for every user. Supabase's own daily backups (§5 of `docs/DEPLOYMENT_SETUP.md`, ~7 day retention on the free tier) are a reasonable baseline but aren't something this app controls directly, aren't encrypted under a key this app manages, and disappear if the Supabase project itself is ever deleted or downgraded. This is a second, independent, encrypted, app-controlled backup.

## How it works

`app/Console/Commands/BackupDatabase.php` (scheduled daily at 02:00 UTC in `routes/console.php` via `backup:database`):

1. `pg_dump -Fc` (custom format — supports selective `pg_restore`, not just full-DB restore) against the app's own DB connection config (`config/database.connections.pgsql`), whatever it's currently pointed at (works identically in dev/staging/production).
2. Encrypts the dump with `gpg --encrypt --recipient <key>` against a **public** key — see "The backup key" below.
3. Uploads the encrypted `.dump.gpg` file to the `backups` filesystem disk (`config/filesystems.php`) — a distinct disk/bucket from the app's normal `s3` disk, pointed at a genuinely off-VPS provider (Backblaze B2, Cloudflare R2, or similar), not the app's own self-hosted RustFS instance. A same-box backup doesn't survive losing the VPS itself, which defeats the point.
4. Deletes backups older than `BACKUP_RETENTION_DAYS` (default 30) from that disk on every run — flat retention, not a tiered daily/weekly/monthly rotation. Simple on purpose; revisit only if storage cost becomes a real concern.

The command **skips cleanly** (exit 0, informational log line, not an error) if `BACKUP_GPG_RECIPIENT` isn't set — safe to leave scheduled on any environment, including ones (like a throwaway staging box) that haven't set up a backup key.

A second command, `app/Console/Commands/CheckBackupFreshness.php` (`backup:check-freshness`, scheduled daily at 06:00, four hours after the backup job), verifies a recent, non-empty backup file actually landed on the disk — without ever touching the decryption key (see "Restoring" below for why an automated *restore* drill isn't possible here by design). Both scheduled commands call `Log::critical(...)` on failure (`routes/console.php`) — container logs already flow to Loki (`infrastructure/.env.production`'s logging comment), so a Grafana alert rule watching for these log lines is the intended alerting path; nothing else is wired up yet.

## The backup key

**Deliberately a separate GPG keypair from `APP_KEY`**, not reused. `APP_KEY` encrypts live PHI at rest in the running database (`app/Models/MedicalInformation.php`'s `encrypted` casts — see `docs/ARCHITECTURE.md`); if it ever leaked, you'd want that to be a contained incident, not one that also hands over every historical backup. Keeping them separate means a leak of one doesn't compromise the other.

**The private key never touches the VPS.** Only the public key is needed to *encrypt* backups (that's what GPG asymmetric encryption is for) — the server can produce backups it cannot itself decrypt. This is the actual security property: even a fully compromised VPS only yields backups an attacker can't read.

### One-time setup

Generate the keypair **on your own machine, not the VPS**:

```sh
gpg --full-generate-key
# Choose: RSA and RSA (default), 4096 bits, key does not expire (or a long expiry — see rotation note below)
# Name/email: anything identifying, e.g. "Mediscan Backups <you@yourdomain>"
```

This creates a keypair in your local GPG keyring. Then:

1. **Export and back up the private key somewhere durable and offline-ish** — a password manager entry (as a file attachment) is the right place, per the earlier plan discussion:
   ```sh
   gpg --export-secret-keys --armor "Mediscan Backups" > mediscan-backup-private.asc
   ```
   Store `mediscan-backup-private.asc` in your password manager, then delete the local file. **If this key is lost, every existing backup becomes permanently unrecoverable** — there is no recovery path, by design (that's what makes it real encryption).

2. **Export the public key** and get it onto the VPS/container so the backup command can encrypt against it:
   ```sh
   gpg --export --armor "Mediscan Backups" > mediscan-backup-public.asc
   ```
   Import it wherever `backup:database` actually runs (inside the `app`/`scheduler` container, since GPG's keyring is per-user/per-container filesystem):
   ```sh
   gpg --import mediscan-backup-public.asc
   ```
   This needs to happen once per container image/volume — if the GPG keyring lives in an ephemeral container filesystem (likely, given the Docker-based deploy), either (a) bake the `gpg --import` into the image build/entrypoint (`infrastructure/docker/app/entrypoint.sh` or the `Dockerfile`), reading the public key from a mounted secret/env var, or (b) mount a persistent volume for `/root/.gnupg` in `docker-compose.production.yml`'s `scheduler` service so the import survives container restarts. The public key itself is not sensitive — it's fine to commit its identifying recipient value (see below) or bake the import step into version-controlled infra.

3. **Set `BACKUP_GPG_RECIPIENT`** (in the `production` GitHub Environment, alongside the other production secrets/variables per `docs/DEPLOYMENT_SETUP.md` §3.4) to the key's email/fingerprint/ID — whatever you passed as `--recipient` when testing, e.g. the email used at generation time.

4. **Set `BACKUP_AWS_ACCESS_KEY_ID`/`BACKUP_AWS_SECRET_ACCESS_KEY`/`BACKUP_AWS_BUCKET`/`BACKUP_AWS_ENDPOINT`** to the off-VPS S3-compatible provider's credentials (any S3-compatible API works — Backblaze B2, Cloudflare R2, AWS S3 itself).

Until steps 3-4 are done, `backup:database` runs on schedule and no-ops harmlessly (see "How it works" above) — there's no rush or risk in doing this setup incrementally.

### Rotation

Not automated. If you ever need to rotate the backup key: generate a new keypair (steps above), re-encrypt any backups you want to keep readable under the new key (decrypt with the old private key, re-encrypt with the new public key), update `BACKUP_GPG_RECIPIENT` and the imported public key on the server, and retire the old keypair once you're confident nothing still needs it.

This is a different key and a different procedure from rotating `APP_KEY` (the live-PHI encryption key) — see `docs/KEY_ROTATION.md` for that one.

## Restoring

**This is a manual, off-server procedure by design — it cannot be automated.** The whole point of keeping the private key off the VPS (see "The backup key" above) is that nothing running on the server can decrypt a backup, which also means nothing running on the server can *verify* one by actually restoring it. `backup:check-freshness` (above) is the automated safety net for "did the job run and produce a real file" — it's not a substitute for actually testing a restore, which only you, locally, with the private key, can do.

**Test this periodically — an untested backup is not a backup.** At minimum, run this once after initial setup and again any time the schema changes significantly.

1. Download the encrypted backup from the `backups` disk (whatever off-VPS provider you configured).
2. Decrypt it with the **private** key (from your password manager, on your own machine — never upload the private key anywhere):
   ```sh
   gpg --output restored.dump --decrypt 2026-07-22_020000.dump.gpg
   ```
3. Inspect before restoring anything, to confirm it's a real, complete dump:
   ```sh
   pg_restore --list restored.dump | head -20
   ```
4. Restore into a **scratch database**, not directly into production:
   ```sh
   createdb mediscan_restore_test
   pg_restore -d mediscan_restore_test restored.dump
   ```
5. Spot-check row counts / a few known records against what you expect, then drop the scratch database.

## Caveats

- **Use the direct (non-pooled) Postgres connection for backups, not the Supabase pooler.** `infrastructure/.env.production`'s `DB_URL` is the Shared Pooler connection string the app itself uses for normal request traffic (§5 of `docs/DEPLOYMENT_SETUP.md`) — poolers can be incompatible with some `pg_dump` operations. If `backup:database` is failing or producing incomplete dumps against production, check whether it needs a separate, direct connection string rather than reusing the app's pooled `DB_URL`.
- This command currently reuses whatever connection the app is configured with (`config('database.default')`) — fine for dev/staging where there's one straightforward connection, worth revisiting for production if the pooler caveat above turns out to matter in practice.
- Retention is flat (delete anything older than `BACKUP_RETENTION_DAYS`), not tiered. If you want "keep dailies for 2 weeks, weeklies for 2 months, monthlies for 6 months" later, that's a bigger change to `pruneOld()` in `BackupDatabase.php` — not implemented now since a solo-dev app's backup volume doesn't yet justify the complexity.
