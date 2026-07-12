# Scheduled tasks

Recurring maintenance commands are registered in `routes/console.php` via the `Schedule` facade. This document is the source of truth for what runs, when, and what it deletes — none of this is covered by Scribe (no HTTP routes) or BROADCASTING.md (no WebSocket events).

## Running the scheduler

The schedule only fires if something invokes it:

- **Local development**: `php artisan schedule:work` (runs the scheduler in the foreground, ticking every minute). Note that `composer run dev` does **not** start it — start it in a separate terminal if you need scheduled commands to fire locally.
- **Staging/production (containerized)**: the `scheduler` service (`infrastructure/docker-compose.{staging,production}.yml`) runs `php artisan schedule:work` as its own long-running process from the application image, supervised by Docker's `restart: unless-stopped` — no host crontab involved. Chosen over a host `cron` entry calling `schedule:run` because it needs zero extra OS-level dependency inside the image and avoids container-vs-host timezone mismatches.

Every command below can also be run manually at any time (`php artisan <command>`).

## Registered tasks

| Command | Schedule | What it does |
|---|---|---|
| `invitations:prune` | Daily (00:00) | Hard-deletes user invitations that expired without being accepted (`accepted_at` null and `expires_at` in the past). Implemented in `UserInvitationRepository::pruneExpired()`; also available to admins as a button (`POST /admin/invitations/prune`). |
| `professional-applications:prune` | Daily (00:00) | Permanently removes stale professional applications — status `pending_review`, `denied`, or `auto_rejected` with no state change (`updated_at`) for **5 days** (override with `--days=N`). Deletes each application's entire per-application S3 folder (ID photo, selfie frames, liveness flash frames, CoE) before force-deleting the row, including soft-deleted rows since rejection soft-deletes. Implemented in `ProfessionalApplicationService::prune()`. |

## Data-loss notes

- `professional-applications:prune` removes **pending-review** applications too: an application no admin reviewed within the threshold is gone for good — KYC files included — and the applicant must reapply. Approved and processing applications are never pruned.
- Both prunes are hard deletes (`delete()` / `forceDelete()`); there is no recovery besides backups.

## Adding a new scheduled task

1. Create the command in `app/Console/Commands/` using the `#[Signature]` / `#[Description]` attributes (see `PruneProfessionalApplications` for the pattern). Keep the logic in a service; the command should only call it and print the result.
2. Register it in `routes/console.php` with `Schedule::command(...)`.
3. Add a feature test that covers the behavior **and** asserts the command is on the schedule with the expected cron expression (see `tests/Feature/PruneProfessionalApplicationsTest.php`, "is scheduled to run daily") so it can't silently fall off.
4. Add it to the table above.
