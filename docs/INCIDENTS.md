# Incidents

Postmortems for production/staging incidents — root cause, fix, and prevention. Newest first.

---

## 2026-07-16 — Admin invitation acceptance returns 502 Bad Gateway

**Symptom**: accepting an admin invitation from Resend email results in a 502 Bad Gateway error.

**Root cause**: Resend API response headers exceeded nginx's default `proxy_buffer_size` (4k). When nginx encounters a response with headers larger than the configured buffer, it cannot handle the proxied response and returns 502.

**Fix**: increased `proxy_buffer_size` to 32k and `proxy_buffers` to 8x32k across all upstream proxy locations in `infrastructure/docker/nginx/templates/{app,cdn,monitor}.conf.template`. This allows nginx to handle larger response headers from Resend and other upstreams without buffering failures.

**Blast radius**: admin invitation flow only — affects any new admin sign-ups via email invitation.

---

## 2026-07-16 — cadvisor silently reporting zero per-container metrics

**Symptom**: while adding a RustFS row to the `Services` Grafana dashboard, discovered that the existing "Container CPU"/"Container memory" panels (App, Scheduler, Reverb rows; also the generic per-container panels in `Host & Containers`) have never shown any data, for any service.

**Root cause**: this Docker Engine (29.6.1) uses the containerd-snapshotter storage layout (`driver-type: io.containerd.snapshotter.v1`), the default since Docker 25+. The deployed cadvisor, `gcr.io/cadvisor/cadvisor:v0.49.1`, only knew how to read the legacy overlay2 graphdriver's layerdb files (`/var/lib/docker/image/overlayfs/layerdb/mounts/<id>/mount-id`), which don't exist under the new layout — every container failed with `Failed to identify the read-write layer ID for container ...` and was silently dropped, leaving cadvisor exposing only host-level systemd/cgroup slices, no actual container metrics.

Initial investigation tested `gcr.io/cadvisor/cadvisor:v0.52.1` (believed to be latest at the time) against the same host — same failure, which looked like a genuine upstream incompatibility rather than a stale-version problem. **That belief was wrong**: cadvisor's release registry moved from `gcr.io/cadvisor/cadvisor` to `ghcr.io/google/cadvisor` at v0.53.0, so `gcr.io/cadvisor/cadvisor` silently stopped receiving new tags after that point — `v0.52.1` was not actually the latest, current releases (`v0.60.5` as of this writing) only exist on the new registry. Once found and tested from the correct registry, `v0.60.5` worked immediately — no `Failed to identify...` errors, real per-container CPU/memory data for every container including RustFS.

**Fix**: bumped the image to `ghcr.io/google/cadvisor:v0.60.5` in both `docker-compose.{staging,production}.yml`. No Docker daemon changes needed — the containerd-snapshotter storage layout stays on, cadvisor's newer container-discovery code just supports it now.

Two more invasive approaches were explored and discarded along the way, kept here for context in case v0.60.x ever regresses:
- Reverting Docker's storage driver to the legacy overlay2 graphdriver (`"features": {"containerd-snapshotter": false}` in `/etc/docker/daemon.json` + daemon restart) — would have fixed cadvisor at the cost of a full-stack bounce and going backward on Docker's own storage roadmap. Not needed once the real fix was found.
- Replacing cadvisor with a small custom exporter that polls the Docker Engine API directly instead of reading cgroup/layerdb files — prototyped and validated working (all 20 containers, ~2s/scrape), but discarded once the version-bump fix was found; no need for custom code when upstream already works.

**Blast radius**: monitoring-only, no application impact — CPU/memory panels were just empty, nothing alerted on them.

---

## 2026-07-16 — Staging KYC uploads silently failing ("missing from storage")

**Symptom**: professional applications on staging were being flagged `PendingReview` with `verification_notes` like "ID photo file missing from storage; manual review required." — the graceful-degradation path added in `3e62cdd`/`bfaa888`, working as designed but surfacing a real underlying failure.

**Root cause**: the `mediscan` S3 bucket did not exist on staging's RustFS instance. Nothing in the deploy pipeline ever created it. `config/filesystems.php` sets `'throw' => false` on the `s3` disk, so every `UploadedFile::store()` call at submission time silently returned `false` instead of throwing — and `ProfessionalApplicationService::insertApplication()` never checked that return value, so `false` was persisted as `id_photo_path` (stored as the string `'0'` in Postgres). The "missing from storage" message only appeared later, downstream, when `ProcessProfessionalApplication` tried to read a path that was never valid to begin with.

Confirmed via staging RustFS: `listBuckets()` returned zero buckets, `HeadBucket` on `mediscan` returned 404. Credentials weren't the issue — auth succeeded fine against the (bucket-less) store. All 4 affected staging rows had `id_photo_path = '0'`.

**Fix**:
- Created the missing bucket on staging directly (`storage:ensure-bucket`, see below) and confirmed a real upload/read/delete round-trip.
- Closed the code gap: `ProfessionalApplicationService` now checks every `store()`/`storeAs()` return value via a `storeOrFail()` helper and throws `ProfessionalApplicationUploadFailedException` on failure instead of persisting a bad path (`app/Services/Kyc/ProfessionalApplicationService.php`, `app/Exceptions/ProfessionalApplicationUploadFailedException.php`, wired into both web and API renderers in `bootstrap/app.php`).
- Added `php artisan storage:ensure-bucket` (`app/Console/Commands/EnsureStorageBucketExists.php`) — idempotent, creates the configured bucket if missing. Wired into `.github/workflows/deploy.yml` right after migrations, so every deploy (staging, production, or any future environment) self-heals a missing bucket before traffic hits it.
- Added `php artisan professional-applications:reprocess {id?} {--missing-storage}` (`app/Console/Commands/ReprocessProfessionalApplication.php`) to re-dispatch KYC verification for applications stuck in `PendingReview` once a storage issue is resolved.

**Blast radius**: staging only — 4 test applications, all already denied/soft-deleted, nothing to recover. Production was not yet deployed to the VPS at the time of the incident, so this landed before production could hit the same issue.

**Prevention**: the `storage:ensure-bucket` deploy step is the durable fix — bucket existence is no longer an implicit assumption baked into infra that nothing provisions. The `storeOrFail()` check means any *future* storage outage fails the submission loudly and immediately instead of silently corrupting `id_photo_path` and only surfacing during async processing.
