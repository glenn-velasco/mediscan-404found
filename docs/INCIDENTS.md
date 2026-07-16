# Incidents

Postmortems for production/staging incidents — root cause, fix, and prevention. Newest first.

---

## 2026-07-16 — cadvisor silently reporting zero per-container metrics

**Symptom**: while adding a RustFS row to the `Services` Grafana dashboard, discovered that the existing "Container CPU"/"Container memory" panels (App, Scheduler, Reverb rows; also the generic per-container panels in `Host & Containers`) have never shown any data, for any service.

**Root cause**: this Docker Engine (29.6.1) uses the containerd-snapshotter storage layout (`driver-type: io.containerd.snapshotter.v1`), the default since Docker 25+. cadvisor's Docker container handler only knows how to read the legacy overlay2 graphdriver's layerdb files (`/var/lib/docker/image/overlayfs/layerdb/mounts/<id>/mount-id`), which don't exist under the new layout — every container fails with `Failed to identify the read-write layer ID for container ...` and is silently dropped, leaving cadvisor exposing only host-level systemd/cgroup slices. Confirmed this isn't a version issue by test-running the latest cadvisor release (v0.52.1) against the same host with the standard mount set, and separately with `--containerd` pointed at the containerd socket — same failure both times.

**Status**: not yet fixed — deliberately deferred as a separate maintenance action rather than bundled into a routine deploy.

The actual fix is a host-level Docker daemon change: set `"features": {"containerd-snapshotter": false}` in `/etc/docker/daemon.json` so Docker falls back to the classic overlay2 graphdriver layout cadvisor understands, then restart the Docker daemon. This is materially riskier than an application deploy — restarting the daemon briefly interrupts every container on the host, and containers typically get recreated from their images rather than resuming in place (named volumes are unaffected). Given the blast radius (the whole staging stack, on a live shared host), this will be done as its own isolated, watched step, not folded into the next `git push`.

An alternative was prototyped and validated — replacing cadvisor with a small exporter that polls the Docker Engine API directly (`GET /containers/json` + `GET /containers/{id}/stats?stream=false`) instead of reading cgroups/layerdb files, emitting the same metric names/labels (`container_cpu_usage_seconds_total{name=...}`, `container_memory_usage_bytes{name=...}`) so no Grafana query changes were needed. It worked (confirmed live against all 20 running containers, ~2s per scrape) but the decision was to fix cadvisor itself instead, so this wiring was reverted; the exporter source is kept at `infrastructure/docker/docker-stats-exporter/` unwired, in case it's wanted later as a fallback.

**Blast radius**: monitoring-only, no application impact — CPU/memory panels are just empty, nothing alerts on them.

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
