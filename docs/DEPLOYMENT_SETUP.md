# Deployment setup

This is not covered by Scribe — Scribe only documents HTTP routes under `api/v1/*`. This document is the source of truth for how the staging/production VPS is set up and how CI/CD reaches it. See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the conceptual system design and [`infrastructure/README.md`](../infrastructure/README.md) for day-to-day commands.

**Layout**: 9 sections, grouped by topic rather than chronological order — Cloudflare, SSH, GitHub Environments, Resend, Supabase, the GitHub Actions workflows themselves, Google Cloud Vision, and public-repo hygiene. Cross-references use these numbers (e.g. "§3.2").

Nearly everything here is automated by Ansible (`infrastructure/provision/`) once the VPS is provisioned that way (§6.2) — the "manual way" shown in a few places is what Ansible replaces, kept only as historical reference for understanding what the roles actually do.

---

## 1. Cloudflare setup

### 1.1 Domain + nameservers

Bought one VPS and one domain (`mediscan.cloud`) from Hostinger. One-time, per domain — happens once ever, not worth automating:

1. Add `mediscan.cloud` to Cloudflare (free plan) → Cloudflare issues two nameservers (yours will be specific to your zone, e.g. `conrad.ns.cloudflare.com` / `georgia.ns.cloudflare.com`).
2. In Hostinger's domain panel, replace the nameservers with the two Cloudflare gave you.
3. Wait for Cloudflare to show the zone as **Active**.

### 1.2 DNS records

Root domain and `www` stay on Hostinger's own hosting (not the VPS) — Hostinger's website builder / static hosting:

| Type | Name | Target |
|---|---|---|
| CNAME | `@` | `connect.hostinger.com` |
| CNAME | `www` | `connect.hostinger.com` |

Also set up MX records for a general inbox (e.g. `contact@mediscan.cloud`) if wanted — unrelated to Resend, which verifies its own sending domain separately (§4).

### 1.3 VPS → Cloudflare

The VPS serves the app subdomains, each an **A record → the VPS IP, proxied** (orange cloud):

| Type | Name | Result | Status |
|---|---|---|---|
| A | `app` | `app.mediscan.cloud` | done |
| A | `staging` | `staging.mediscan.cloud` | done |
| A | `cdn` | `cdn.mediscan.cloud` | **still needed** - RustFS/CDN reverse proxy (`infrastructure/docker/nginx/templates/cdn.conf.template`) won't be reachable without this |
| A | `cdnstaging` | `cdnstaging.mediscan.cloud` | **still needed**, same reason |
| A | `monitor` | `monitor.mediscan.cloud` | **still needed** - Grafana reverse proxy (`infrastructure/docker/nginx/templates/monitor.conf.template`) won't be reachable without this |
| A | `monitorstaging` | `monitorstaging.mediscan.cloud` | **still needed**, same reason |

Hostnames are kept flat (one level under `mediscan.cloud`, not nested like `cdn.staging.app.mediscan.cloud`) so they're covered by Cloudflare's free Universal SSL / origin cert wildcard without needing Advanced Certificate Manager.

**Whenever a hostname is added, renamed, or removed** (here, in the `NGINX_APP_HOST`/`NGINX_CDN_HOST`/`NGINX_MONITOR_HOST` env vars in `docker-compose.{staging,production}.yml`'s `nginx` service, and in the `tls_domains`/DNS-record lists in `.github/workflows/provision.yml`), re-run `provision.yml` (§6.2) afterward — it's what actually creates/updates the Cloudflare A record *and* reissues the origin cert to cover the new hostname list. Changing those env vars alone does nothing to Cloudflare; Nginx will just fail TLS for a hostname that isn't on the cert yet.

Don't flip these to proxied (orange cloud) until the VPS is actually serving valid HTTPS on those hostnames — see §1.4-1.5 first. If you're provisioning via Ansible (§6.2), the workflow's "Point DNS at this VPS" step does this for all six automatically on every run, upserting by record name.

### 1.4 SSL/TLS — Cloudflare edge settings

SSL/TLS → Overview:
- Mode: **Full (Strict)** — validates the origin cert instead of just encrypting the hop (Full alone accepts a self-signed cert with no validation; Flexible doesn't encrypt the Cloudflare→origin hop at all).

SSL/TLS → Edge Certificates:
- **Always Use HTTPS**: on.
- **Certificate Transparency Monitoring**: on (email when a CA issues a cert for your domain — an early signal if something issues one you didn't request). This one stays a manual dashboard toggle regardless of which path provisions the box — no confirmed simple Cloudflare API endpoint for it.

If you're provisioning via Ansible (§6.2), the workflow's "Configure Cloudflare zone SSL settings" step sets Mode and Always Use HTTPS automatically via the Cloudflare API on every run — only Certificate Transparency Monitoring needs the manual toggle above.

### 1.5 SSL/TLS — origin certificate (CSR / origin server)

**Ansible does this end-to-end** (§6.2) — skip straight to that section if you're using it. The `tls` role calls Cloudflare's Origin CA API itself, generates the private key + CSR *on the VPS* (the private key never leaves it), requests a cert covering all six hostnames in one shot, and writes it straight to `/etc/mediscan/tls/origin.crt`/`origin.key`. Issued for 15 years, so no renewal reminder needed.

<details>
<summary>Manual way (historical — only relevant if you're not using Ansible)</summary>

Cloudflare dashboard → SSL/TLS → Origin Server → Create Certificate:
- Key type: **ECC**.
- Hostnames: needs to cover **all six** app subdomains (`app.mediscan.cloud`, `staging.mediscan.cloud`, `cdn.mediscan.cloud`, `cdnstaging.mediscan.cloud`, `monitor.mediscan.cloud`, `monitorstaging.mediscan.cloud`) or a wildcard (`*.mediscan.cloud` + `mediscan.cloud`) — the one `nginx` image (`infrastructure/docker/nginx/templates/*.conf.template`) is shared by both stacks, each rendering only its own 3 hostnames into `server_name` at container start (via `NGINX_APP_HOST`/`NGINX_CDN_HOST`/`NGINX_MONITOR_HOST`), but the cert itself still needs to cover all six since either stack could end up serving from this VPS.
- Validity: **30 days** if matching a monthly billing cycle — **this means the cert needs manual renewal every 30 days, or the site goes down.** Put a recurring reminder on this; there's no auto-renewal wired up on this path.
- Save the certificate and key as `yourdomain.pem` / `yourdomain.key`.

Place them on the VPS at the exact path the compose files expect (`docker-compose.staging.yml`/`docker-compose.production.yml` both bind-mount `/etc/mediscan/tls` read-only into the `nginx` container):

```sh
sudo mkdir -p /etc/mediscan/tls
sudo cp yourdomain.pem /etc/mediscan/tls/origin.crt
sudo cp yourdomain.key /etc/mediscan/tls/origin.key
sudo chmod 600 /etc/mediscan/tls/origin.crt /etc/mediscan/tls/origin.key
```

</details>

### 1.6 Google site verification (optional)

Not tied to anything in the app — nothing in the codebase reads a Google verification value. Search Console for `mediscan.cloud` registers as a **Domain** property, which only offers **DNS TXT record** verification (no HTML tag option). Since Cloudflare already manages this domain's DNS (§1.2/1.3), add it there: Cloudflare DNS → Add record → type `TXT`, name `@`, content whatever Google gives you. A Domain property automatically covers every subdomain (`app.mediscan.cloud`, `staging.mediscan.cloud`, etc.) and both protocols once verified, so this one record is enough — no per-subdomain verification needed. No app changes needed either way.

`GOOGLE_ANALYTICS_ID` (GA4 measurement ID) *is* read by the app — see `resources/views/app.blade.php`, wired up via a **Variable** in the GitHub **production** Environment (Settings → Environments → production → Variables).

---

## 2. SSH setup

### 2.1 Personal SSH access

Generate your own keypair (same regardless of which path provisions the VPS):

```sh
ssh-keygen -t ed25519 -C "your-alias-or-email"
```

This writes **two files**. Whatever path you accept at the `Enter file in which to save the key` prompt (default `~/.ssh/id_ed25519`) becomes the **private** key — no extension, never leave your machine, never commit it. `ssh-keygen` adds `.pub` to that same path for the **public** key — the one you paste elsewhere. If you ever see a bare filename with no `.pub` twin missing, that's the private half.

Run this from `~/.ssh/`, not from inside this repo — accepting a bare filename (or your email) as the save path while sitting in the repo directory drops both files into the working tree as untracked files, one keystroke away from `git add -A` committing your private key.

**If provisioning via Ansible** (§6.2): put the public half in the `ADMIN_SSH_PUBLIC_KEY` variable in the `provisioning` GitHub Environment (§3.2), and the `users` role installs it into `mediscan`'s `authorized_keys` on every run. You never touch the VPS by hand for this.

<details>
<summary>Manual way (historical)</summary>

```sh
# as the mediscan user
mkdir -p ~/.ssh
nano ~/.ssh/authorized_keys   # paste the public key, save
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
sudo systemctl restart ssh
```

</details>

Verify it worked: `ssh mediscan@<vps-ip>`.

### 2.2 CI deploy SSH access

A **separate** keypair from your personal one, used only by GitHub Actions:

```sh
# on your own device
ssh-keygen -t ed25519 -C "github_actions_deployment"
```

Same private (no extension) / public (`.pub`) split as §2.1 — run it from `~/.ssh/`, not from inside this repo.

**If provisioning via Ansible**: the public half goes in the `DEPLOY_SSH_PUBLIC_KEY` variable in the `provisioning` Environment (§3.2), and the `users` role installs it the same way as the personal key above — this deploys as `mediscan` itself (the same sudo-capable user, not a separate least-privileged deploy user). The **private** half goes into `deploy.yml`'s `SSH_PRIVATE_KEY` secret instead (§3.4) — that part is unrelated to Ansible and always manual, since a private key should never pass through a playbook run.

<details>
<summary>Manual way (historical)</summary>

```sh
# on the VPS, as mediscan
echo "paste the .pub contents here" >> ~/.ssh/authorized_keys
sudo systemctl restart ssh
```

</details>

**How to confirm CI can actually log in**: you can't test the CI private key from your own machine (it only exists in the `SSH_PRIVATE_KEY` GitHub secret). Trigger a `deploy.yml` run (push to `main`) and check the **"Load deploy SSH key"** and **"Add host to known_hosts"** steps in the Actions log — if those pass and the subsequent `ssh` commands don't hang or reject, the key is correctly installed.

### 2.3 What Ansible itself needs

A **third** keypair, used exactly once per provisioning run and unrelated to the two above:

```sh
ssh-keygen -t ed25519 -C "mediscan_bootstrap" -f ~/.ssh/mediscan_bootstrap
```

The `-f` here pins the save path explicitly, so this one produces `~/.ssh/mediscan_bootstrap` (private) and `~/.ssh/mediscan_bootstrap.pub` (public) regardless of where you run it from.

At OS-reinstall (or OS-install) time, your provider lets you paste a public SSH key for `root` (on Hostinger: the reinstall panel has a field for it) — paste this key's public half there. Ansible's `users` role uses it once, as root, to create the `mediscan` user and install the two keys above, then **disables root login and password auth entirely** (`PermitRootLogin no`, `PasswordAuthentication no` in `sshd_config`) — the ordering (install keys, verify they're on disk, *then* disable root) is deliberate, so a failed key-install can't lock you out. After that run, the bootstrap key stops working on that box (root login is off) — you'll need it again only when setting up another box, whether that's reinstalling this same VPS or provisioning a brand-new one; the same keypair works for either, since it isn't tied to one machine.

Its private half goes into the `PROVISION_SSH_PRIVATE_KEY` secret in the `provisioning` GitHub Environment (§3.2) — `provision.yml` loads it to make the initial root connection.

**Net result after a provisioning run**: `mediscan`'s `authorized_keys` has 2 entries (your personal key, the CI deploy key); root login and password auth are both off; the only way in is as `mediscan` with one of those two keys.

---

## 3. GitHub environment setup

Three separate GitHub Environments exist, each with its own secrets/variables, used by different workflows:

| Environment | Used by | When |
|---|---|---|
| `provisioning` | `provision.yml` | manual (`workflow_dispatch`) — only when actually setting up/resetting the VPS |
| `staging` | `deploy.yml` | every push to `main` |
| `production` | `deploy.yml` | every tag matching `*.*.*` |

Plus **repository-level** secrets/variables (Settings → Secrets and variables → Actions, *not* under any Environment) for values that are identical across all of them.

`deploy.yml` pins its jobs to `environment: staging` or `environment: production` depending on the trigger; GitHub resolves environment-scoped secrets/variables first and only falls back to repository-level ones of the same name. Put each value at the level that matches how it actually varies — get this wrong and either both environments share a value that should differ (e.g. one DB used for both), or an environment-only value never gets set and deploys with an empty string.

### 3.1 Repository-level (shared by everything)

Settings → Secrets and variables → Actions → lands on **Repository secrets**; there's a **Variables** tab next to it for the non-secret ones.

| Name | Kind | Where the value comes from |
|---|---|---|
| `SSH_PRIVATE_KEY` | secret | private half of the CI deploy key (§2.2) |
| `SSH_HOST` | secret | the VPS IP |
| `SSH_USER` | secret | `mediscan` |
| `RESEND_API_KEY` | secret | Resend dashboard → API Keys (§4) |
| `GOOGLE_CLOUD_VISION_KEY_BASE64` | secret | base64 of the Cloud Vision service-account JSON key (§8) — one key shared by both environments |
| `APP_NAME` | variable | `Mediscan` |
| `AWS_DEFAULT_REGION` | variable | `us-east-1` (RustFS doesn't care about the region, but the S3 client requires *some* value) |
| `AWS_BUCKET` | variable | `mediscan` |
| `GOOGLE_CLOUD_PROJECT` | variable | GCP project ID that owns the Cloud Vision service account (§8) |

`GITHUB_TOKEN` (auto-provided) covers pushing/pulling images from GHCR — no PAT needed anywhere.

### 3.2 `provisioning` Environment

Only needed once you're actually setting up or resetting the VPS via Ansible (§6.2) — skip this until then. Keep it entirely separate from `staging`/`production`; it only runs on manual `workflow_dispatch`, never on a push.

**Create it**: Settings → Environments → New environment → name it exactly `provisioning` (must match `environment: provisioning` in `.github/workflows/provision.yml`) → Configure environment. Its own page has separate **Environment secrets** → **Add secret** and **Environment variables** → **Add variable** buttons — not the same buttons as §3.1.

**Its entries** (2 required secrets + 1 optional, 3 variables):

| Name | Kind | Where the value comes from |
|---|---|---|
| `PROVISION_SSH_PRIVATE_KEY` | secret | private half of the bootstrap key (§2.3) — `cat ~/.ssh/mediscan_bootstrap`, paste the whole output including `-----BEGIN...`/`-----END...`. Only works for the *first* run against a given box — see `ADMIN_SSH_PRIVATE_KEY` below for re-runs |
| `ADMIN_SSH_PRIVATE_KEY` | secret, optional | private half of your personal key (§2.1) — the same key `ADMIN_SSH_PUBLIC_KEY` below is the public half of. The bootstrap key only authenticates as `root`, and the `users` role permanently disables root SSH login on every run, so `provision.yml` can only connect once per box unless this is set. With it, the workflow auto-detects that root login is closed and falls back to connecting as the sudo-capable `mediscan` user instead — needed to re-run the playbook later (e.g. to reissue the TLS cert after adding a hostname). Leave unset if you'd rather always re-run the playbook by hand from your own machine as `mediscan` — see §6.2 |
| `CLOUDFLARE_API_TOKEN` | secret | select `mediscan.cloud` zone → **API Tokens** → **Create Token** → name it `CLOUDFLARE_API_TOKEN` → **Edit policy** → **Specified Domains** → search "SSL and Certificates" → check **Edit** → search "dns" → check **Edit** on both **DNS** and **Zone DNS Settings** → search "zone settings" → check **Edit** on **Zone Settings** (needed for the "Configure Cloudflare zone SSL settings" workflow step, which PATCHes `/settings/ssl` and `/settings/always_use_https` — without this permission Cloudflare returns a 403 "Unauthorized to access requested resource") → **Review token** → **Create token** → copy the token value (shown once) |
| `CLOUDFLARE_ZONE_ID` | variable | Cloudflare dashboard → select the `mediscan.cloud` zone → right sidebar of **Overview** → **Zone ID** |
| `DEPLOY_SSH_PUBLIC_KEY` | variable | public half of the CI deploy key (§2.2) — same key `SSH_PRIVATE_KEY` (§3.1) is the private half of, reused so the box trusts the exact key it's deployed with |
| `ADMIN_SSH_PUBLIC_KEY` | variable | public half of your personal key (§2.1) |

When done, the `provisioning` Environment's page lists 2-3 secrets and 3 variables (3 secrets if you added `ADMIN_SSH_PRIVATE_KEY`). This Environment and its entries stay permanently configured — it only runs on manual `workflow_dispatch` (never on a push), and you may need it again for a future VPS reset or reprovision run.

### 3.3 `staging` Environment

Settings → Environments → New environment → `staging` → Configure → Deployment branches and tags → Selected branches and tags → Add rule → Branch → `main`.

Then fill in its own secrets/variables (its own **Environment secrets**/**Environment variables** buttons, same pattern as §3.2):

| Name | Kind | Value |
|---|---|---|
| `APP_KEY` | secret | generate per §3.5 — a staging-only key |
| `DB_PASSWORD` | secret | pick a password for staging's own local Postgres container, e.g. `openssl rand -hex 20` — the container gets created fresh from this value, so any password you choose becomes correct |
| `DB_DATABASE` | variable | `mediscan` |
| `DB_USERNAME` | variable | `mediscan` |
| `APP_URL` | variable | `https://staging.mediscan.cloud` |
| `AWS_URL` | variable | `https://cdnstaging.mediscan.cloud/mediscan` |
| `VITE_REVERB_HOST` | variable | `staging.mediscan.cloud` |
| `APP_DEBUG` | variable | `true` |
| `MAIL_FROM_ADDRESS` | variable | `noreply@mediscan.cloud` — must be on the `mediscan.cloud` domain verified with Resend (§4), or Resend rejects the send |
| `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` | secret | self-issued — pick any values (e.g. `openssl rand -hex 20` twice), staging's RustFS container just needs to agree with itself |
| `REVERB_APP_ID` | variable | self-issued, e.g. `1` |
| `REVERB_APP_SECRET` | secret | self-issued, e.g. `openssl rand -hex 32` |
| `VITE_REVERB_APP_KEY` | variable | self-issued, e.g. `openssl rand -hex 16` |
| `VITE_REVERB_PORT` | variable | `443` |
| `VITE_REVERB_SCHEME` | variable | `https` |
| `MACHINE_LEARNING_SHARED_SECRET` | secret | self-issued, e.g. `openssl rand -hex 32` — only needs to match between `app` and `machine-learning` *within* staging, nowhere else |
| `GRAFANA_ADMIN_PASSWORD` | secret | pick a password for staging's Grafana login |
| `ADMIN_EMAIL` | variable | email for the admin account, e.g. `admin@mediscan.cloud` |
| `ADMIN_PASSWORD` | secret | password for the admin account |

Leave `DB_URL` **unset** — Laravel falls back to `DB_PASSWORD`/`DB_DATABASE`/`DB_USERNAME` above when it's empty (§5 explains why staging uses a local container instead). `DB_HOST` doesn't need an entry here — the deploy workflow hardcodes it to `postgres` (the compose service name) in the shipped `.env`.

`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` don't need entries here either — those are the **server-side** broadcaster's connection settings (`config/broadcasting.php`), and `deploy.yml` hardcodes them to `reverb`/`8080`/`http` (the Reverb container's internal address on the Docker network, no TLS). Don't confuse these with `VITE_REVERB_HOST`/`VITE_REVERB_PORT`/`VITE_REVERB_SCHEME` above, which are for the **browser-facing** Echo client and correctly point at the public `https://` hostname through nginx — reusing the `VITE_REVERB_*` values for the server-side vars was a past bug (server tried to reach `https://reverb:443`, which doesn't exist inside the network, instead of `http://reverb:8080`).

### 3.4 `production` Environment

Settings → Environments → New environment → `production` → Configure → Deployment branches and tags → Selected branches and tags → Add rule → Tag → `*.*.*`.

Same secrets/variables pattern:

| Name | Kind | Value |
|---|---|---|
| `APP_KEY` | secret | generate per §3.5 — a **different** key from staging's, never shared |
| `DB_URL` | secret | Supabase pooler connection string (§5 — not done yet, blocks a real production deploy until it is) |
| `APP_URL` | variable | `https://app.mediscan.cloud` |
| `AWS_URL` | variable | `https://cdn.mediscan.cloud/mediscan` |
| `VITE_REVERB_HOST` | variable | `app.mediscan.cloud` |
| `APP_DEBUG` | variable | `false` |
| `MAIL_FROM_ADDRESS` | variable | `noreply@mediscan.cloud` — same address as staging is fine, since both share the one verified Resend domain (§4) |
| `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` | secret | self-issued, separate values from staging's |
| `REVERB_APP_ID` | variable | self-issued, e.g. `2` (different from staging) |
| `REVERB_APP_SECRET` | secret | self-issued, separate from staging's |
| `VITE_REVERB_APP_KEY` | variable | self-issued, separate from staging's |
| `VITE_REVERB_PORT` | variable | `443` |
| `VITE_REVERB_SCHEME` | variable | `https` |
| `MACHINE_LEARNING_SHARED_SECRET` | secret | self-issued, separate from staging's |
| `GRAFANA_ADMIN_PASSWORD` | secret | pick a password for production's Grafana login |
| `ADMIN_EMAIL` | variable | email for the admin account, e.g. `admin@mediscan.cloud` |
| `ADMIN_PASSWORD` | secret | password for the admin account |

`DB_PASSWORD`/`DB_DATABASE`/`DB_USERNAME` aren't needed here — production has no local Postgres container, and they're ignored once `DB_URL` is set.

As in §3.3, `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` don't need entries — `deploy.yml` hardcodes the server-side broadcaster to the internal `reverb:8080` container address (plain `http`), separate from the `VITE_REVERB_*` vars above which drive the public-facing Echo client.

Why `staging`/`production` need separate values for `APP_KEY`, `RUSTFS_*`, `REVERB_APP_SECRET`, etc. even though some are "self-issued with no external account behind them": each environment runs its own independent set of containers (own RustFS, own Reverb, own Grafana). Nothing breaks technically if you reuse a value across both, but separate values mean a staging leak doesn't also expose production.

### 3.5 Generating `APP_KEY`

There's no running app to run `artisan` on yet at this point (no containers up, and you shouldn't reuse your local dev key). Generate one throwaway per environment, e.g. via Sail locally:

```sh
./vendor/bin/sail artisan key:generate --show
```

This just prints a `base64:...` key without writing it anywhere — run it twice (once per environment) and paste each result into that environment's `APP_KEY` secret. Do **not** reuse the key already in your local `.env`.

---

## 4. Resend (email)

1. Create an account, add the `mediscan.cloud` domain.
2. Add the DNS records Resend gives you, via Cloudflare (§1.2) — wait for propagation.
3. Generate an API key → goes into the repository-level `RESEND_API_KEY` secret (§3.1).
4. Set `MAIL_FROM_ADDRESS` (e.g. `noreply@mediscan.cloud`) in both the `staging` and `production` Environment variables (§3.3/§3.4) — Laravel otherwise falls back to its framework default of `hello@example.com` (`config/mail.php`), which Resend rejects since that domain isn't verified on the account.

---

## 5. Supabase (production database)

**Not yet done** — this blocks a real production deploy until it is (§3.4's `DB_URL`).

1. Create a project.
2. Use the **Shared Pooler** connection string, not the direct one. As of 2026, Supabase's pooling has two tiers: the **Shared Pooler** (powered by **Supavisor**, Supabase's own PgBouncer-wire-compatible pooler) is free and available on every project including the free tier; the **Dedicated Pooler** (literally PgBouncer, a separate box) is Pro-plan-and-up only. Use the Shared Pooler connection string from the project's Connect dialog — `app`/`horizon`/`scheduler`/`reverb` are four separate long-running Postgres clients, and pooling avoids exhausting the free tier's direct-connection limit.
3. Goes into the `DB_URL` secret in the `production` Environment (§3.4).

Supabase's default daily backups (~7 day retention, free tier) cover the production DB reasonably. Staging's local Postgres container and RustFS object data in both environments have no off-VPS backup — worth a follow-up scheduled export (e.g. to Backblaze B2/Cloudflare R2) once things are otherwise stable.

### 5.1 Enforcing TLS on the DB connection

`config/database.php`'s `pgsql` connection reads `'sslmode' => env('DB_SSLMODE', 'prefer')` — `prefer` will silently fall back to plaintext if the server doesn't offer TLS, which isn't acceptable for a database holding PHI. `infrastructure/.env.production` sets `DB_SSLMODE=require`, forcing the connection to fail closed instead of downgrading if TLS isn't available.

Supabase's pooler endpoints support TLS by default, so this should just work — but verify it after setting `DB_URL` (§5 above), don't assume:

```sh
# from the deployed app container
docker compose exec app psql "$DB_URL&sslmode=require" -c "SELECT 1;"
```

If that fails, the pooler connection string needs adjusting before `DB_SSLMODE=require` will let the app connect at all.

**`infrastructure/.env.dev.example` intentionally does *not* set this** — the local dev Postgres container (`docker-compose.dev.yml`) has no TLS certificate configured, so requiring SSL there would break local dev entirely. Dev traffic never leaves the isolated docker-compose network, so `prefer` (the default) is an acceptable gap locally; `require` is only enforced where it matters — production, where the connection crosses to Supabase's infrastructure over the internet.

This is the DB-connection half of encryption in transit. The other half — Cloudflare ↔ origin, browser/mobile ↔ Cloudflare — is already covered by §1.4's **Full (Strict)** mode requirement; both need to hold for PHI to be protected end-to-end in transit.

---

## 6. Workflows

Four GitHub Actions workflows, in `.github/workflows/`:

| Workflow | Trigger | What it does |
|---|---|---|
| `lint.yml` | push/PR to `develop`/`main`/`master`/`workos` | static checks |
| `tests.yml` | push/PR to same branches (also reusable via `workflow_call`) | test suite |
| `deploy.yml` | push to `main` → staging; push tag `*.*.*` → production | build 7 images → GHCR, SSH-deploy, migrate, seed roles, health-check |
| `provision.yml` | manual (`workflow_dispatch`) | one-time/on-demand VPS setup via Ansible |

### Order of operations (fresh or reset VPS)

For a first-time setup or VPS reset:

1. **Push to GitHub first** — commit your local changes and push to `main`. All three workflow files (`.github/workflows/`) and the infrastructure code need to exist on GitHub before Actions can run them; until then, `provision.yml` and `deploy.yml` won't even appear in the **Actions** tab.
2. **Run `provision.yml`** (§6.2) — manually trigger it via `workflow_dispatch` in Actions. This is the one-time VPS setup: creates the `mediscan` user, installs Docker, configures firewall, generates the origin cert, installs SSH keys. **This must complete first** — without it, the box has no app user, no Docker, and no SSH access beyond `root`, so `deploy.yml` will fail.
3. **Push to `main` to deploy staging** (§6.1) — now that the VPS is ready, every push to `main` automatically builds and ships the app containers, runs migrations, seeds roles. Can be re-triggered as many times as needed after step 2, without re-running `provision.yml` again.
4. **Tag for production** — push a semver tag like `v1.0.0` to trigger `deploy.yml` against the production Environment instead.

**Note:** If you push to `main` before provisioning completes, `deploy.yml` will also auto-trigger but fail at the SSH/user-creation step (expected, not a bug). Let provisioning finish, then push again — the same code push will succeed the second time.

### 6.1 Triggering a deploy

Push to `main` → deploys staging. Tag a commit (`git tag v0.1.0 && git push origin v0.1.0`) → deploys production. Watch the run under the **Actions** tab; the `build` and `deploy` jobs show which Environment they resolved to. Needs §3.1, §3.3 (staging) and/or §3.4 (production) filled in first, and Settings → Actions → General → Workflow permissions → **Read and write permissions** (needed for `GITHUB_TOKEN` to push images to GHCR).

### 6.2 Running `provision.yml` (Ansible)

Automates §1.3-1.5 (DNS A-records, Cloudflare SSL settings, origin cert) and user/firewall/Docker setup on the VPS, in one run, for the one VPS that serves both environments — all six hostnames on one cert, the shared CI deploy key, your personal admin key. Needs §2.3 (bootstrap key) and §3.2 (`provisioning` Environment) done first.

**Re-run this any time the hostname list changes** (`tls_domains`/DNS loop in `provision.yml`, and the corresponding `nginx` conf files) — it's the only thing that actually creates the Cloudflare DNS record and reissues the origin cert to cover a new/renamed hostname; deploying the app itself (§6.1) doesn't touch DNS or TLS at all.

**Prerequisite: a fresh VPS for the first run.** `provision.yml` itself never touches the OS install or reinstalls anything — it only connects over SSH and configures whatever's already running. What the *first* run needs going in is root SSH access via the bootstrap key (§2.3) on a box that doesn't already have conflicting state. That's naturally true for:
- **A brand-new VPS** — already fresh the moment you buy it, nothing to do here.
- **An existing VPS you want to reset** — only in this case do you reinstall the OS first, from your provider's panel, pasting the bootstrap key's public half for `root` at reinstall time. **This wipes the box** — confirm there's nothing on it you need first (per §5, Supabase/production DB isn't wired up yet, so there shouldn't be live data — double-check `docker compose ps` and any volumes on the VPS first if in doubt). Reinstalling is a manual, destructive, one-off action in your provider's panel, not something `provision.yml` does or triggers — you'd only repeat it if you deliberately wanted to wipe the box again later.
- **A new or additional VPS** — reuse the same bootstrap keypair from §2.3 (`mediscan_bootstrap.pub`); it isn't tied to any particular box. Paste its public half into the new VPS's root SSH key field at creation time, the same way you would for a reinstall, and point `provision.yml`'s `host` input at the new IP. No need to generate a new bootstrap keypair — the existing `PROVISION_SSH_PRIVATE_KEY` secret still matches it.

1. **Confirm the VPS is fresh** (via one of the two paths above), with the bootstrap key's public half already accepted as `root`'s authorized key.

   If your provider's image already came with Docker, or already has the base utilities, you can skip either or both of `base`/`docker` at run time via the `skip_base`/`skip_docker` inputs below. **Check with `dpkg`, not by running the package name as a command** — several of these don't expose a binary matching their own package name (`ca-certificates` only installs cert files + `update-ca-certificates`; `gnupg` provides `gpg`, not `gnupg`; `unattended-upgrades`' binary is singular, `unattended-upgrade`) — so typing the name at a prompt gives a false "not found" even when it's installed:
   ```sh
   dpkg -s curl git ufw ca-certificates gnupg unattended-upgrades openssl docker-ce 2>&1 | grep -E "Package|not installed"
   ```
   Anything reported "installed ok" is safe to skip; anything "not installed" (or missing entirely) still needs that role to run. Neither skip is required either way — both roles check what's already installed and no-op cleanly, so running them against an already-set-up box is just a couple minutes slower, never wrong.
   - **`skip_base`** — covers `curl`/`git`/`ufw`/`ca-certificates`/`gnupg`/`unattended-upgrades`/`openssl` together (one tag, all-or-nothing) — near-universal on almost any cloud image, Docker-branded or not, but confirm all seven with the command above rather than assuming.
   - **`skip_docker`** — only relevant if the image specifically came with Docker preinstalled. Check for `docker-ce` by name, specifically — if the image instead has Ubuntu's own `docker.io` package, the `docker` role can't tell they're the same thing and would install `docker-ce` alongside it rather than skipping, so only set `skip_docker` once `dpkg -s docker-ce` itself reports installed.

2. **Run the workflow**, on GitHub itself (not from your terminal) — safe to run as many times as needed, including repeatedly against the same already-provisioned box, since every role it runs is idempotent:
   - Repo on github.com → **Actions** tab → left sidebar → **provision** (under "All workflows").
   - **Run workflow** dropdown (top right) → branch `main` → fill in **host** (VPS IP) → leave **skip_base**/**skip_docker** unchecked unless step 1 applies → green **Run workflow** button.
   - A new run appears within a few seconds (refresh if not) — click in to watch progress. Requires manual approval only if you've added a required reviewer to the `provisioning` Environment; otherwise starts immediately.
   - Expected steps in order: checkout → resolve values → load SSH key(s) → install Ansible → run playbook (the longest step) → configure Cloudflare SSL → point DNS. All green = it worked.
   - The "Run playbook" step first probes whether `root@<host>` still accepts the bootstrap key; if so it connects as `root` (first run on a fresh box — this is also what does the actual hardening). If root login is already closed (any box that's completed a successful run before), it falls back to connecting as `mediscan` with sudo — which only works if you've set the optional `ADMIN_SSH_PRIVATE_KEY` secret (§3.2). Without that secret, a re-run against an already-hardened box fails at this step with `Permission denied (publickey,password)` — in that case, re-run the playbook by hand instead (see the command in the note below).
   - If it fails on **"Run playbook"** on what should be a *first* run: almost always the bootstrap key doesn't match what you pasted as `root`'s key when the box was created or reinstalled, or `known_hosts` couldn't reach the IP (VPS still booting).

3. **Verify access**:
   - As yourself: `ssh mediscan@<vps-ip>` using your personal key (§2.1) — confirms `ADMIN_SSH_PUBLIC_KEY` installed correctly.
   - For CI: trigger a `deploy.yml` run and confirm its **"Load deploy SSH key"** step succeeds (§2.2) — confirms `DEPLOY_SSH_PUBLIC_KEY` installed correctly.
   - The origin cert is already in place at `/etc/mediscan/tls/origin.crt`/`origin.key` — nothing to copy manually.

4. **Deploy**: §6.1 — `SSH_HOST`/`SSH_USER`/`SSH_PRIVATE_KEY` (§3.1) still point at the same IP and user, unchanged.

Every Ansible role is idempotent (`roles/*/tasks/main.yml`), so re-running `provision.yml` later (e.g. to add a firewall rule, rotate keys, or reissue the TLS cert for a new hostname) converges rather than redoing or reinstalling completed work — it's the *connecting* part that changes after the first run (see the `root`-vs-`mediscan` note in step 2 above), not the playbook's safety to rerun.

If you didn't set `ADMIN_SSH_PRIVATE_KEY` and need to rerun against an already-hardened box, do it from your own machine as `mediscan` instead of via the workflow:
```sh
cd infrastructure/provision
ansible-playbook playbook.yml -i "<vps-ip>," -u mediscan --become \
  --private-key ~/.ssh/<your-personal-key> \
  -e deploy_ssh_public_keys='["<deploy pubkey>"]' \
  -e admin_ssh_public_keys='["<your pubkey>"]' \
  -e cloudflare_api_token="<token>" \
  -e tls_domains='["app.mediscan.cloud","staging.mediscan.cloud","cdn.mediscan.cloud","cdnstaging.mediscan.cloud","monitor.mediscan.cloud","monitorstaging.mediscan.cloud"]' \
  -e deploy_user_sudo=true
```

---

## 7. RustFS object storage

RustFS (S3-compatible) stores all user-uploaded files: KYC documents, professional application photos, and profile avatars. Both staging and production run their own RustFS container on the internal Docker network; the public CDN hostname (`cdn.mediscan.cloud` / `cdnstaging.mediscan.cloud`) is a read-only nginx reverse proxy in front of it.

### 7.1 How it works

The Laravel app writes to RustFS directly over the internal network (`AWS_ENDPOINT=http://rustfs:9000`). Mobile and browser clients read files through the public CDN hostname (`AWS_URL`), which nginx proxies to RustFS:

```
Mobile app  →  https://cdn.mediscan.cloud/mediscan/avatars/...
                  ↓ (Cloudflare → nginx → limit_except GET/HEAD)
              http://rustfs:9000/mediscan/avatars/...
```

The `AWS_URL` GitHub variable **must include the bucket name as a path prefix** (e.g. `https://cdn.mediscan.cloud/mediscan`, not just `https://cdn.mediscan.cloud`). Laravel's S3 adapter generates public URLs by appending the object key to `AWS_URL`; without the bucket prefix, the resulting URLs point at the wrong path and return 404 from RustFS.

### 7.2 Bucket policy

`storage:ensure-bucket` (run on every deploy, see §6.1) creates the bucket if missing **and** sets an anonymous read policy so the CDN proxy can serve files without signing each request:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": "*",
    "Action": ["s3:GetObject"],
    "Resource": ["arn:aws:s3:::mediscan/*"]
  }]
}
```

This is safe because the CDN nginx config already restricts to `limit_except GET HEAD { deny all; }` — no writes through the public hostname. The internal `AWS_ENDPOINT` (used for writes) is only reachable from within the Docker network.

### 7.3 What uses S3

| Feature | Disk | Code path |
|---|---|---|
| Profile avatars | `s3` | `MedicalInformationService::storeAvatarImage()`, `MedicalInformation::$avatar` accessor |
| KYC ID photos | `s3` | `ProfessionalApplicationService::storeResizedImageOrFail()` |
| Professional application photos | `s3` | Same as above |

All three use `Storage::disk('s3')` — the `FILESYSTEM_DISK=s3` env var makes this the default. The `public` disk (local filesystem) is unused in staging/production; it only exists for local dev without RustFS.

### 7.4 Backup note

RustFS data (avatar uploads, KYC photos) lives in a Docker volume on the VPS with no off-box backup. The production database gets Supabase's daily backups, but object storage does not. Consider a scheduled `mc mirror` to an off-VPS S3 provider (Backblaze B2, Cloudflare R2) once the volume of stored files grows — see [`BACKUPS.md`](BACKUPS.md) for the existing DB backup approach.

## 8. Google Cloud Vision (OCR + face detection)

As of [this change](../docs/ARCHITECTURE.md#kyc-ocr--face-detection), `config('kyc.ocr_driver')`/`config('kyc.face_driver')` default to `google`, backed by `App\Services\Kyc\GoogleVisionKycClient` — the `machine-learning` sidecar (§ARCHITECTURE.md) stays deployed as the `sidecar` fallback, but isn't what a fresh deploy uses by default. This section is what a real staging/production deploy needs for the `google` driver to actually work.

### 8.1 GCP project + service account

1. Create (or reuse) a GCP project. Enable the **Cloud Vision API** for it (APIs & Services → Enable APIs and Services → search "Cloud Vision API" → Enable).
2. IAM & Admin → Service Accounts → **Create Service Account** (e.g. `mediscan-vision`) → skip the "Grant this service account access to project" step entirely (**Continue** → **Done** with no role assigned). Cloud Vision's image-annotation calls (`FACE_DETECTION`, `DOCUMENT_TEXT_DETECTION`) don't authorize against a project resource the way Cloud Storage/BigQuery do, so a role-less service account works as long as the Cloud Vision API is enabled and billing is active on the project. If a real call later fails with `PERMISSION_DENIED`, go back and grant `roles/serviceusage.serviceUsageConsumer` — **not** any of the "Vision AI" roles the IAM role picker suggests when you search "Vision"; those belong to a different product (Vertex AI Vision, for video analytics), not the classic Cloud Vision API this integration calls.
3. On the new service account → **Keys** → **Add Key** → **Create new key** → JSON. This downloads a `.json` file once — there's no way to re-download it, only revoke and reissue.

### 8.2 Getting the key into config — no file, ever

Unlike the TLS origin cert (§1.5), this credential never touches disk on the VPS (or locally) as a file at all — `GoogleVisionKycClient` decodes it from an env var straight into a `Google\Auth\Credentials\ServiceAccountCredentials` object in memory (`config('services.cloud_vision.key_base64')`), so there's no key file to secure, mount, or accidentally leave behind on a box:

1. Base64-encode the downloaded key so it survives being pasted into a single-line env var/secret:
   ```sh
   base64 -w0 mediscan-vision-key.json
   ```
2. **Repository secret, not an Environment secret** — Settings → Secrets and variables → Actions. That page opens directly on **Repository secrets** (no Environment selected); this is a different list from the one you land on after clicking into Settings → Environments → `staging` or `production`. Click **New repository secret** → name it `GOOGLE_CLOUD_VISION_KEY_BASE64` → paste the base64 output → **Add secret**.

   One key shared by both `staging` and `production` this way — same reasoning as `RESEND_API_KEY` being repo-level (§3.1): this integration isn't environment-scoped the way `APP_KEY`/`REVERB_APP_SECRET`/etc. are (those genuinely need to differ per environment), so a separate service account per environment is unnecessary unless you specifically want to isolate the Vision API quota/billing between them. A repo-level secret is automatically visible to every Environment's jobs unless that Environment defines a same-named secret of its own to override it.
3. Same **Repository secrets** page → **Variables** tab → **New repository variable** → name it `GOOGLE_CLOUD_PROJECT` → value is the project ID the service account belongs to.

`deploy.yml`'s **"Generate and ship .env"** step writes `GOOGLE_CLOUD_VISION_KEY_BASE64`/`GOOGLE_CLOUD_PROJECT` straight into the shipped `.env` alongside everything else — no separate step, no extra volume mount on `app`/`horizon`/`scheduler`/`reverb`. Locally (`sail`), the same var goes in your own `.env` (already gitignored); see `.env.example` for the placeholder.

### 8.3 Verifying it worked

Service name differs by where you're running this — locally via `compose.yaml`/Sail it's `laravel.test` (already running, so use `exec`); on the deployed VPS via `docker-compose.staging.yml`/`docker-compose.production.yml` it's `app` (use `run --rm`, or `exec` if it's already up):

```sh
# local (sail)
docker compose exec laravel.test php artisan tinker --execute="echo get_class(app(App\Contracts\Kyc\OcrClientContract::class)).PHP_EOL;"

# deployed VPS
docker compose run --rm app php artisan tinker --execute="echo get_class(app(App\Contracts\Kyc\OcrClientContract::class)).PHP_EOL;"
```
should print `App\Services\Kyc\GoogleVisionKycClient`. To confirm the app can actually reach the API, trigger a KYC flow that calls OCR/face detection, or call `detectText()`/`compare()` directly against a real image via tinker, and watch for `KycSidecarUnavailableException` in the logs — it wraps both "the base64 secret is missing/invalid" and any real Vision API call failure (bad credentials, API not enabled, quota exceeded), so check the exception message to tell which.

### 8.4 Falling back to the sidecar

If Vision access isn't set up yet, or you want to temporarily revert: SSH in and hand-edit `$REMOTE_DIR/.env` on the VPS, setting `KYC_OCR_DRIVER=sidecar` and/or `KYC_FACE_DRIVER=sidecar`, then `docker compose up -d` to restart the app containers with the new config — no rebuild needed, since both drivers ship in the same image. This isn't wired into `deploy.yml`/GitHub Environments (would reset to `google` on the next deploy); it's a manual break-glass step, not a supported toggle.

---

## 9. Mobile app deep linking (App Links / Universal Links)

When users click the email verification link on their phone, the OS can show an "Open with MediScan" prompt instead of opening the browser. This requires serving two verification files on the `app.mediscan.cloud` domain.

### 9.1 How it works

1. User receives verification email on their phone
2. Email contains: `https://app.mediscan.cloud/api/v1/verify-email/{id}/{hash}?signature=...`
3. OS checks if the app is installed and the domain is verified
4. If verified: "Open with MediScan" prompt appears → app opens directly
5. If not verified (or app not installed): browser opens → server redirects to `mediscanmobile://email-verified`

### 9.2 Server-side files

The Laravel app serves the verification files at:

- `/.well-known/assetlinks.json` — Android App Links
- `/.well-known/apple-app-site-association` — iOS Universal Links

Both are served by `WellKnownController` (`app/Http/Controllers/WellKnownController.php`) and return 404 if the config values are missing (safe for local dev).

### 9.3 Configuration

Add these to your production `.env`:

```bash
# Android: SHA-256 fingerprint of the signing certificate
# Get it with: eas credentials --platform android
ANDROID_SHA256_FINGERPRINT=AA:BB:CC:...

# iOS: Apple Developer Team ID (10 characters)
# Find it at https://developer.apple.com/account -> Membership
IOS_TEAM_ID=ABC1234567
```

These are read by `config/services.php` → `app_links` section.

### 9.4 Getting the SHA-256 fingerprint

```bash
# From EAS (production builds)
eas credentials --platform android

# From local keystore (debug builds)
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android

# From installed APK
keytool -printcert -jarfile app.apk
```

### 9.5 Mobile app configuration

The Expo app (`app.json`) is already configured with:

- **Android**: `intentFilters` for `https://app.mediscan.cloud/api/v1/verify-email`
- **iOS**: `associatedDomains` for `applinks:app.mediscan.cloud`

These only take effect in production builds (EAS). Development builds use the custom scheme (`mediscanmobile://`) as a fallback.

### 9.6 Verifying it worked

After deploying with the config values set:

1. **Android**: Open `https://app.mediscan.cloud/.well-known/assetlinks.json` in a browser — should return JSON with your package name and SHA-256 fingerprint
2. **iOS**: Open `https://app.mediscan.cloud/.well-known/apple-app-site-association` in a browser — should return JSON with your Team ID and app ID
3. **Test on device**: Send a verification email, tap the link on your phone — should show "Open with MediScan" (Android) or open the app directly (iOS)

### 9.7 Troubleshooting

| Issue | Cause | Fix |
|---|---|---|
| No "Open with" prompt | `ANDROID_SHA256_FINGERPRINT` not set | Set in production `.env` and redeploy |
| Browser opens instead of app | App not installed or domain not verified | Install the app, check `assetlinks.json` is served correctly |
| 404 on `.well-known` files | Config values are empty | Set `ANDROID_SHA256_FINGERPRINT` and/or `IOS_TEAM_ID` in `.env` |
| Deep link opens app but shows wrong screen | Route not registered in `_layout.tsx` | Check `email-verified` is in the `guard={ready}` block |

---

## 10. Notes for public repo

The repo is currently private; if/when it goes public, run a one-time git-history secret scan first (e.g. `gitleaks detect` or `trufflehog` over the **full history**, not just the current tree) — going public exposes every past commit, not just HEAD. `infrastructure/.env`/`.env.production` are blank-value templates and should stay that way forever; real values only ever go into the GitHub secrets/variables above, never into a tracked file. After going public, verify GitHub secret scanning + push protection are enabled as an ongoing safety net.
