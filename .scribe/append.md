# Deployment

For the full setup runbook and system diagrams, see
[`docs/DEPLOYMENT_SETUP.md`](../docs/DEPLOYMENT_SETUP.md) and
[`docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md) — this section is a
condensed summary for developers reading the API docs, not the operational
source of truth.

## Architecture Overview

```
                         Cloudflare (TLS + edge cache)
                                │
                        app.mediscan.cloud
                       staging.mediscan.cloud
                    cdn.*, cdnstaging.*, monitor.*
                                │ :443 (Origin CA cert, Full Strict)
                    ┌───────────┴────────────┐
                    │  Nginx (port 80 → 301, │
                    │  port 443 ssl + http2) │
                    │  server blocks by host │
                    └───┬───────┬────────┬───┘
                        │       │        │
                   ┌────┘   ┌───┘    ┌───┘
                   ▼        ▼        ▼
                 app      reverb   rustfs
              (Octane/   (WS,     (CDN read
              FrankenPHP) :8080)   proxy, :9000)
```

The application runs on a single VPS using Docker Compose, one `docker-compose.{staging,production}.yml`
stack per environment (deployed to separate directories, not shared). Nginx
terminates TLS and is the only publicly reachable service; every other
container sits on an `internal` Docker network. `app` runs Laravel Octane on
FrankenPHP (not PHP-FPM) — there is no separate web server process in front
of PHP, Octane serves HTTP directly and Nginx reverse-proxies to it.

## Services

| Service | Role | Port | Notes |
|---|---|---|---|
| **nginx** | TLS termination, routing, static asset caching | 80/443 (public) | Only service on the `public` network |
| **app** | Octane/FrankenPHP app server | 8000 (internal) | Serves `web.php` and `api/v1` |
| **horizon** | Laravel Horizon (queue worker + dashboard) | — | `php artisan horizon` |
| **scheduler** | Task scheduler | — | `php artisan schedule:work` |
| **reverb** | WebSocket broadcasting | 8080 | Public path is `/app/` via Nginx; backend services reach it directly over `internal` |
| **face-match** | OCR + face-match + liveness sidecar (Python) | 8500 | |
| **redis** | Cache, sessions, queue, Reverb scaling | 6379 | |
| **rustfs** / **rustfs-permissions** | S3-compatible object storage; `rustfs-permissions` is a one-shot `chown` init container | 9000 | |
| **postgres** | PostgreSQL 18 | 5432 | **Staging only** — production uses Supabase via `DB_URL` |
| **prometheus** | Metrics scraping (14d retention) | — | |
| **grafana** | Dashboards (Loki + Prometheus datasources) | 3000 | Reverse-proxied publicly at `monitor.*` via Nginx |
| **loki** | Log storage | 3100 | |
| **promtail** | Docker log scraper → Loki | — | Reads `/var/run/docker.sock` |
| **node-exporter** | Host metrics | — | |
| **cadvisor** | Per-container metrics | — | |

Every image (`app`, `nginx`, `face-match`, `prometheus`, `grafana`, `promtail`, `loki` — 7 total)
is built and pushed to `ghcr.io/<owner>/mediscan-<name>` on every push; the
only difference between staging and production deploys is which compose file
and GitHub Environment secrets are used, not the images themselves.

## TLS

The origin uses a **Cloudflare Origin CA certificate** (ECC), not Let's
Encrypt/certbot. It's created manually in the Cloudflare dashboard
(SSL/TLS → Origin Server), covers all six app subdomains (or a wildcard),
and is placed on the VPS at `/etc/mediscan/tls/origin.{crt,key}` — bind-mounted
read-only into the `nginx` container (see `app.conf`/`cdn.conf`/`monitor.conf` in
`infrastructure/docker/nginx/conf.d/`). Cloudflare SSL/TLS mode is **Full
(Strict)**, so Cloudflare validates this cert against its own CA before
proxying.

**This cert was issued with 30-day validity** (matched to the VPS billing
cycle) and has **no automated renewal** — it must be manually recreated and
redeployed every 30 days, or the site goes down. The automated alternative
(Ansible + Cloudflare API-issued certs) exists in `infrastructure/provision/roles/tls/`
but isn't wired into the current manual VPS.

## Domains

| Domain | Environment | Status |
|---|---|---|
| `app.mediscan.cloud` | Production | Live |
| `staging.mediscan.cloud` | Staging | Live |
| `cdn.mediscan.cloud` | Production object storage | **DNS record still needed** |
| `cdnstaging.mediscan.cloud` | Staging object storage | **DNS record still needed** |
| `monitor.mediscan.cloud` | Production Grafana | **DNS record still needed** |
| `monitorstaging.mediscan.cloud` | Staging Grafana | **DNS record still needed** |

All are A records → the VPS IP, proxied (orange cloud) through Cloudflare.
Hostnames are kept flat (one level under `mediscan.cloud`) so the origin
cert / Cloudflare's free Universal SSL covers them without a wildcard on a
nested subdomain.

## CI/CD Pipeline

Defined in `.github/workflows/deploy.yml`, one workflow for both environments
(no separate retag-on-tag step — every push rebuilds all 7 images fresh):

```
push to main / push tag *.*.*
  → prep (decide image tag + target environment from the ref)
  → test (Pest suite) + lint, in parallel
  → build: matrix-build and push all 7 images to GHCR
  → deploy:
      - SSH: ship infrastructure/docker-compose.<env>.yml to the VPS
      - SSH: write .env from GitHub Environment secrets + vars
      - SSH: docker compose pull
      - SSH: docker compose run --rm app artisan migrate --force
      - SSH: docker compose run --rm app artisan db:seed --class=RoleAndPermissionSeeder --force
      - SSH: docker compose up -d --remove-orphans
      - SSH: poll /up until healthy (30 x 5s)
      - SSH: docker image prune -f --filter until=72h
```

Branch `main` → `staging` GitHub Environment, auto-deploy. A version tag
(`*.*.*`) → `production` GitHub Environment, gated on manual approval.
`RoleAndPermissionSeeder` is idempotent and safe to re-run on every deploy.

File delivery is SSH piping (`ssh ... "cat > file" < local-file`), not `scp` —
no file ever touches disk on the runner beyond the checkout, and nothing is
copied by hand.

Deploy paths are **per-environment**, not shared: `~/mediscan/staging/` and
`~/mediscan/production/` on the VPS (`REMOTE_DIR: mediscan/${environment}`,
relative to the `mediscan` user's home — that user isn't root and has no
write access to `/opt`).

## GitHub Secrets & Variables

| Secret | Scope | Purpose |
|---|---|---|
| `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` | Repository | SSH deploy access |
| `APP_KEY` | Per-environment | Laravel app key |
| `DB_URL` (production) / `DB_PASSWORD` (staging) | Per-environment | Database credentials |
| `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` | Per-environment | Also used as `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` |
| `REVERB_APP_SECRET` | Per-environment | Server-side only |
| `RESEND_API_KEY` | Per-environment | Transactional email |
| `FACE_MATCH_SHARED_SECRET` | Per-environment | Shared with the face-match sidecar |
| `GRAFANA_ADMIN_PASSWORD` | Per-environment | Grafana admin login |

Plus repository **variables** (non-secret): `APP_NAME`, `APP_DEBUG`, `APP_URL`,
`DB_DATABASE`, `DB_USERNAME`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`,
`VITE_REVERB_HOST`, `VITE_REVERB_APP_KEY`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.

There is no `CLOUDFLARE_API_TOKEN` or `CERTBOT_EMAIL` secret in the deploy
workflow — those only apply to the separate, optional Ansible provisioning
path (`infrastructure/provision/`, `.github/workflows/provision.yml`), used
for bootstrapping *new* VPSs, not the current one.

## GitHub Environments

| Environment | Deployment rule | Approval |
|---|---|---|
| `staging` | Branch: `main` | Auto-deploy (no approval) |
| `production` | Tag: `*.*.*` | Manual approval required |

## Release Process

```bash
# 1. Ensure main has passed CI/CD and is deployed to staging
# 2. Verify staging is working
# 3. Create and push a version tag
git tag 1.0.0
git push origin 1.0.0
# 4. Go to GitHub Actions → approve the production deploy
# 5. CI/CD runs migrate --force + db:seed + docker compose up -d
```

## Object Storage (RustFS / CDN)

RustFS (S3-compatible) is proxied through Nginx at the `cdn.*` subdomains,
read-only (`limit_except GET HEAD { deny all; }` in
`infrastructure/docker/nginx/conf.d/cdn.conf`) — the app writes to RustFS
directly over the internal network (`AWS_ENDPOINT=http://rustfs:9000`), never
through the public CDN path. `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` and
RustFS's own access/secret keys are the same values, sourced from the
`RUSTFS_ACCESS_KEY`/`RUSTFS_SECRET_KEY` secrets.

```
Browser → cdn.mediscan.cloud/<bucket>/abc.jpg
  → Cloudflare proxy (edge cache)
  → VPS :443 (Nginx cdn.conf)
  → http://rustfs:9000/<bucket>/abc.jpg
```

## Monitoring

Self-hosted observability stack: Loki + Promtail (logs), Prometheus +
node-exporter + cAdvisor (metrics), Grafana (dashboards for both).

Grafana is reverse-proxied by Nginx at `https://monitor.mediscan.cloud`
(production) / `https://monitorstaging.mediscan.cloud` (staging) — see
`infrastructure/docker/nginx/conf.d/monitor.conf`. Log in with
`admin` / `$GRAFANA_ADMIN_PASSWORD`.

Query logs in Grafana **Explore** with the Loki datasource:

```
{container="app"}
{container="horizon"} |= "error"
```

## Local Development

Day-to-day local dev is still Sail (`./vendor/bin/sail up`) at the repo root —
nothing here replaces it. `infrastructure/docker-compose.dev.yml` is a
separate, narrower tool that builds the exact Dockerfiles CI pushes to GHCR,
used occasionally to catch a broken Dockerfile before pushing:

```bash
cp infrastructure/.env.dev.example infrastructure/.env.dev
php artisan key:generate --show   # paste into .env.dev's APP_KEY
# generate a self-signed cert into infrastructure/docker/nginx/dev-tls/ (see infrastructure/README.md)
./vendor/bin/sail down             # stop Sail first, ports overlap
docker compose -f infrastructure/docker-compose.dev.yml up --build
```

App: `https://localhost:8443` (self-signed cert). See
[`infrastructure/README.md`](../infrastructure/README.md) for the full setup,
including the hot-reload override file.

---

# Sync Architecture: Local-First Medical Data

## Overview

Medical record ownership has shifted from the server to the mobile device. MediScan no longer stores plaintext medical content (allergies, transfusion consent, emergency contacts) in the database. Instead:

- **Mobile app** stores and manages all medical data locally on the patient's phone
- **Server** acts as an encrypted relay and authentication authority, never holding plaintext medical content
- **Sync** happens via peer-to-peer (BLE/WiFi) when a professional's phone is physically near a patient's phone, or via encrypted relay when the patient is offline

## Scenario 1: Peer-to-Peer Sync (Patient Device Present)

When both devices are physically close:

1. Patient's phone generates and registers a public key: `POST /v1/device-keys`
2. Professional's phone fetches patient's public key: `GET /v1/professional/patients/{patient}/public-key`
3. Phones establish a BLE/WiFi peer connection (outside the server)
4. Professional encrypts changes to the patient's public key
5. Data is exchanged directly between devices; server plays no role

**Server responsibility:** host the patient's public key for discovery only.

## Scenario 2: Encrypted Relay Sync (Patient Device Absent)

When a professional scans a patient (via QR/wristband/card) but the patient has no device present:

1. Professional's phone displays a pre-generated QR encoding the patient's ID
2. Professional's phone encrypts the update to the patient's public key (client-side)
3. Professional submits sealed ciphertext: `POST /v1/professional/patients/{patient}/envelopes`
4. Server stores the opaque blob in `pending_sync_envelopes` (never decrypts)
5. Server broadcasts a "new data waiting" notification over Reverb to the patient (if online)
6. Patient's phone fetches all pending envelopes: `GET /v1/pending-sync`
7. Patient's phone decrypts locally (private key never leaves device)
8. Patient's phone acknowledges after merge: `POST /v1/pending-sync/{envelope}/ack`

**Server responsibility:** relay encrypted payloads and audit delivery; never decrypt.

## Delivery Guarantees

- **Pending envelopes expire after 90 days** if never retrieved
- **Expired envelopes are logged** (not silently deleted) — professionals can see their attestation wasn't claimed
- **Multiple envelopes are additive** — if two professionals scan while patient is offline, both envelopes wait and are applied in order
- **An `unclaimed` status exists** on the envelope for the case where a scan's patient ID can't be resolved, but nothing currently sets it — the auto-matching/admin-review flow this implies isn't wired up yet

## Audit Trail

All sync operations are logged for compliance:

- `device_key.registered` — patient registered a new device
- `device_key.revoked` — patient revoked/rotated a key
- `envelope.submitted` — professional sent a relay payload
- `envelope.acknowledged` — patient confirmed receipt and decryption
- `envelope.expired` — envelope reached TTL without being claimed

Logs contain only metadata (envelope type, IDs, timestamps), never plaintext medical content.

## Encryption Model

Uses `libsodium` (PHP's `ext-sodium`) sealed boxes (crypto_box_seal):

- **Client generates** an ephemeral key pair on every update
- **Client seals** the plaintext to the patient's public key (only that patient can decrypt with their private key)
- **Client sends** the ciphertext (not the plaintext or key material) to `/v1/professional/patients/{patient}/envelopes`
- **Server stores** the ciphertext as an opaque blob
- **Patient's phone** receives the ciphertext and decrypts with the private key (stored only on their device)

The server never stores, transmits, or has access to the private key or plaintext medical content.