# Infrastructure

Operational quick-reference for what's in this folder and how to use it. For
the conceptual system design (diagrams) see [`docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md);
for one-time external account setup (Supabase, Resend, Cloudflare, SSH keys)
see [`docs/DEPLOYMENT_SETUP.md`](../docs/DEPLOYMENT_SETUP.md).

## What's here

```
infrastructure/
├── docker-compose.dev.yml           # local dev/test - builds the real Dockerfiles
├── docker-compose.dev.override.yml  # opt-in hot-reload overlay
├── docker-compose.staging.yml       # deployed to the "staging" GitHub Environment
├── docker-compose.production.yml    # deployed to the "production" GitHub Environment
├── .env / .env.production           # blank-value reference templates - NEVER put real secrets here
├── .env.dev.example                 # copy to .env.dev for local dev, fill in real (local-only) values
├── docker/                          # Dockerfiles + baked config for every image built in CI
└── provision/                       # Ansible playbook for one-time/on-demand VPS setup
```

## Local development - this project already uses Sail, and that stays your daily tool

`compose.yaml` at the repo root (Sail) is the day-to-day dev environment - `./vendor/bin/sail up`, bind-mounted source, instant PHP changes, no rebuild. **Keep using it as-is.** It already runs Postgres, Redis, Mailpit, RustFS, Reverb, a queue worker, and face-match - nothing about this infra work replaces it.

`infrastructure/docker-compose.dev.yml` is a **different, narrower tool**: it builds the *exact* Dockerfiles CI builds and pushes to GHCR (multi-stage composer/pnpm build, FrankenPHP runtime - not Sail's generic bind-mounted runtime image), so you can catch a broken Dockerfile *before* pushing, not to replace your daily loop. Use it occasionally, before a push you're unsure about - not all day every day.

**Don't run both at once** - they duplicate the same backing services (Postgres/Redis/RustFS/etc.) and will fight over container names and ports. Sail's Mailpit dashboard is already on `8025`; this stack's is deliberately mapped to `8026` to avoid that one collision, but the rest (5432, 6379, 9000/9001, 8080, 8500) do overlap with Sail's forwarded ports, so stop Sail first (`./vendor/bin/sail down`) before bringing this one up:

1. Copy the env template and fill in an app key:

   ```sh
   cp infrastructure/.env.dev.example infrastructure/.env.dev
   php artisan key:generate --show   # paste the output into .env.dev's APP_KEY
   ```

2. Generate a local self-signed TLS cert (browser will warn, that's expected):

   ```sh
   openssl req -x509 -newkey rsa:2048 -nodes -days 365 -subj "/CN=localhost" \
     -keyout infrastructure/docker/nginx/dev-tls/origin.key \
     -out infrastructure/docker/nginx/dev-tls/origin.crt
   ```

3. Build and start the stack in the background:

   ```sh
   cd infrastructure
   docker compose -f docker-compose.dev.yml -f docker-compose.dev.override.yml up -d --build
   ```

4. Confirm everything came up healthy:

   ```sh
   docker compose -f docker-compose.dev.yml -f docker-compose.dev.override.yml ps
   ```

5. If a service isn't healthy (e.g. `postgres`), check its logs:

   ```sh
   docker compose -f docker-compose.dev.yml -f docker-compose.dev.override.yml logs <service> --tail=100
   ```

App: `https://localhost:8443` (self-signed cert, browser will warn). Mailpit: `http://localhost:8026`. Add `-f infrastructure/docker-compose.dev.override.yml` to bind-mount source over the built image for PHP changes without a rebuild - still not a Sail replacement, just makes this stack less painful on the rare occasion you're using it for more than a one-off build check.

## Deploying

Nothing is ever scp'd. Pushing to `main` deploys to staging; pushing a tag matching `*.*.*` (e.g. `v1.2.3`) deploys to production. See the workflow table below and `docs/DEPLOYMENT_SETUP.md` for exactly which secrets each one needs.

| Workflow | Trigger |
|---|---|
| `lint.yml` | push/PR to `develop`/`main`/`master`/`workos` |
| `tests.yml` | push/PR to same branches (also reusable via `workflow_call`) |
| `deploy.yml` | push to `main` → staging; push tag `*.*.*` → production |
| `provision.yml` | manual (`workflow_dispatch`) - one-time/on-demand VPS setup |

The live VPS was originally set up manually (see `docs/DEPLOYMENT_SETUP.md` for the exact steps taken), and is being reset onto the Ansible path (`provision.yml`) documented in that file's §6.2 — see it for the full procedure, including the `provisioning` GitHub Environment setup (§3.2). One VPS serves both staging and production, so `provision.yml` takes a `host` input plus `skip_base`/`skip_docker` toggles, and provisions for both environments in a single run (all six hostnames on one cert, the shared CI deploy key, your personal admin key) — it no longer asks for a target environment name.

## Observability

Grafana is reverse-proxied at `https://monitor.mediscan.cloud` (production) / `https://monitorstaging.mediscan.cloud` (staging) via `infrastructure/docker/nginx/templates/monitor.conf.template` - log in with `admin` / the environment's `GRAFANA_ADMIN_PASSWORD`. Two dashboards are provisioned out of the box: *Host & Containers* and *nginx & App Logs*.
