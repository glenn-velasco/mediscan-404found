# Mediscan Staging Load Testing

Load testing scripts for the Mediscan staging environment using [k6](https://k6.io/), a modern load testing tool.

## Overview

This directory contains:

- **`k6/scenarios.js`** — API-level load test simulating many concurrent users hitting core endpoints (`/scans`, `/medical-information`, sync, etc.). Scales to 200+ concurrent virtual users.
- **`k6/browser.js`** — Browser-based load test using real Chromium instances to validate frontend rendering/JavaScript under backend load. Modest VU count (10-20 VUs) since each is a real browser process.
- **`.github/workflows/load-test.yml`** — GitHub Actions workflow for running tests on-demand against staging.

## Quick Start

### Prerequisites

1. **k6 installed** — https://k6.io/docs/getting-started/installation/
2. **Test account on staging** — Tests default to `test@example.com` / `password`, the account already defined in `database/seeders/DatabaseSeeder.php` (plaintext in source, not a secret — Admin role, pre-verified). This is **not guaranteed to exist on staging automatically**, since `deploy.yml` only seeds roles/permissions on deploy, not the full `DatabaseSeeder`. Verify with a login curl before running a real test — see `SETUP_AND_WORKFLOW.md` for the check and how to seed it if missing.
3. **Environment variables** — Copy `.env.example` to a `.env` file if you want to override the defaults (different account, different base URL).

### Option 1: Run Locally

Best for development and testing the scripts themselves.

```bash
# Copy and configure the environment file
cp loadtest/k6/.env.example loadtest/k6/.env.local
# Edit .env.local with your staging URL and test credentials

# Load the env file and run a dry-run first (1 VU, 1 iteration)
source loadtest/k6/.env.local
k6 run --vus 1 --iterations 1 loadtest/k6/scenarios.js

# Once the dry-run passes, run the full scenario
k6 run loadtest/k6/scenarios.js

# Or run browser tests (requires k6-browser)
k6 run loadtest/k6/browser.js
```

### Option 2: Run from GitHub Actions

Best for scheduled/repeatable runs without using your local resources.

1. **No secrets required to get started** — the workflow defaults to `https://staging.mediscan.cloud` and the `test@example.com` seeded account. Only add repo secrets (Settings → Secrets and variables → Actions) if you want to override these:
   - `STAGING_BASE_URL`
   - `STAGING_TEST_USER_EMAIL`
   - `STAGING_TEST_USER_PASSWORD`

2. **Trigger the workflow** (Actions tab → "Staging Load Test" → "Run workflow"):
   - Choose test type: `api`, `browser`, or `both`
   - Results are uploaded as artifacts (download from the workflow run summary)

### Option 3: Run in k6 Cloud (Recommended for "Real" Tests)

Best for removing local-network bottlenecks and getting hosted dashboards/reports.

1. **Create a free k6 account** — https://app.k6.io/
2. **Get your API token** — Account Settings → API Tokens
3. **Run from local or CI:**

   ```bash
   # Using k6 cloud from your local machine
   export K6_CLOUD_TOKEN=your_token_here
   k6 cloud loadtest/k6/scenarios.js
   
   # Or from GitHub Actions: add K6_CLOUD_TOKEN to repo secrets, then use:
   k6 cloud loadtest/k6/scenarios.js
   ```

## How to Monitor During the Test

While a load test runs, watch your staging environment's Prometheus/Grafana dashboards:

1. **Open Grafana** — http://staging-grafana.mediscan.test (or your Grafana URL)
2. **Monitor key panels:**
   - **App/Web Tier**: CPU, memory, request rate, latency
   - **Database**: Connection count, query latency, active queries
   - **Redis/Horizon**: Queue depth, job throughput, failed jobs
   - **External ML/OCR Sidecar**: If load test is stressing uploads, this is likely your bottleneck

3. **Prometheus metrics endpoint** — `/prometheus` (check `AllowIps` middleware in `config/prometheus.php` if you want external scrapes)

## Test Scenarios Explained

### API Scenario (`auth_and_core_api`)

- **Auth**: One login for the entire test run (in k6's `setup()`, which runs once regardless of VU count), shared by every VU. All VUs impersonate the same seeded account, and Fortify's login limiter is 5/min per email+IP — authenticating per-VU or per-iteration would blow through that almost instantly (429s), so a single shared token is not just an optimization, it's required.
- **Load**: Ramps 0 → 50 → 200 → 100 → 0 VUs over ~4 minutes.
- **Endpoints exercised**:
  - `GET /me` — Current user info
  - `GET /medical-information` — List records
  - `POST /scans` — Log event (lightweight)
  - `POST /medical-information` — Create record
  - `PUT /medical-information/{id}` — Update record
  - `GET /pending-sync` — Sync envelope check
  - `POST /emergency-qr/events` — QR usage logging
  - `DELETE /medical-information/{id}` — Delete record
- **Think time**: 1–3 seconds between iterations (realistic user behavior)
- **Concurrency**: Up to 200 simultaneous VUs

### Browser Scenario (`browser_frontend`)

- **Auth**: Real login form submission via browser
- **Load**: Ramps 0 → 5 → 15 → 0 VUs over ~3 minutes
- **Flows**:
  1. Load homepage
  2. Navigate to login page
  3. Fill form, submit (real form submission)
  4. Wait for redirect to dashboard
  5. Navigate to medical-information page
  6. Check page content renders
  7. Attempt logout
- **Concurrency**: Up to 15 browser VUs (much heavier than protocol-level VUs)

## Pass/Fail Thresholds

The load tests define explicit pass/fail criteria in `options.thresholds`:

- `http_req_failed < 5%` — Allow max 5% HTTP errors (4xx, 5xx)
- `http_req_duration p95 < 2000ms` — 95th percentile latency under 2 seconds
- `api_errors < 5%` — Max 5% application-level errors

If a threshold is crossed, k6 exits with status code 1, failing the CI job.

## Interpreting Results

### Local/CI Output

k6 prints a summary table to stdout:

```
     ✓ login status 200
     ✓ login returns token
     ...
   vus_max.......: 200
   vus........... : 0
   http_req_failed.: 2.3%
   http_req_duration: avg=312.5ms, p95=1203ms, max=4567ms
   ...
```

**Key metrics:**
- `http_req_duration` — How long HTTP requests took
- `http_req_failed` — Percentage of failed requests
- `vus_max` — Peak concurrent VUs achieved
- `group_duration` — Time spent in each logical group/flow
- `api_errors` — Custom error rate tracked by the script

### k6 Cloud Reports

k6 Cloud provides an interactive dashboard with:
- Real-time request/error graphs during the test
- Trend analysis (before/after) if you run the same test multiple times
- Web Vital metrics (FCP, LCP, CLS) for browser tests
- Downloadable JSON results

## Troubleshooting

### "login status 200" checks failing

**Cause**: Test user not found or password incorrect
- Verify the user exists in staging
- Confirm credentials in `.env` or GitHub secrets match exactly
- Check that the email is verified (required for many endpoints)

### "http_req_duration p95 < 2000ms" threshold crossed

**Cause**: Staging is slow or overloaded
- Reduce VU count in the scenario (`--vus 50` flag override)
- Check Grafana for resource bottlenecks (CPU, DB connections)
- If the ML/OCR sidecar is stressed, it will slow down `/professional-applications` uploads

### Browser test hangs or times out

**Cause**: k6-browser requires special setup; headless Chromium may not run in CI
- Browser tests are optional; API tests alone are usually sufficient
- For local browser tests, ensure you have recent Chromium installed
- In GitHub Actions, the browser test gracefully falls back to API-only if k6-browser is unavailable

### "permission denied" or 403 errors

**Cause**: Test user doesn't have required permissions
- Ensure the test user is verified (email-verified)
- Check that the user has access to the endpoints being tested (e.g., `/professional-applications` requires `verified` middleware)
- Admin endpoints (if any) will 403 without proper roles

## Pre-Seeding the Test Account

The default test account (`test@example.com` / `password`) is defined in `database/seeders/DatabaseSeeder.php`, but `deploy.yml` only runs `RoleAndPermissionSeeder` on deploy — not the full seeder — so it may not exist on staging yet. Check first:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://staging.mediscan.cloud/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password"}'
# 200 = ready to test. 401/422 = seed it (below).
```

If missing, seed it once via the running container (idempotent, safe to re-run):

```bash
ssh <ssh_user>@<ssh_host>
cd ~/mediscan/staging
docker compose run --rm app php artisan db:seed --force
```

See `SETUP_AND_WORKFLOW.md` for the full explanation and the single-VPS caveats.

## Running on a Schedule

To run load tests regularly (e.g., daily/weekly health checks):

1. Create a scheduled GitHub Actions workflow (cron-based) that triggers load-test.yml
2. Or set up a cron job on your infrastructure to run `k6 run` locally and post results to a monitoring service

Example cron workflow:

```yaml
name: Scheduled Staging Load Test

on:
  schedule:
    - cron: '0 2 * * MON'  # Every Monday at 2 AM UTC

jobs:
  load-test:
    uses: ./.github/workflows/load-test.yml
    with:
      test_type: both
    secrets: inherit
```

## Next Steps / Advanced Usage

- **Custom load profiles**: Edit the `stages` array in `scenarios.js` to test different ramp patterns (spike, sustained, slow ramp)
- **Fixture data**: Add sample images to `loadtest/fixtures/` and use them in the professional-applications upload scenario
- **Integration with monitoring**: Parse k6 JSON results and send to your own monitoring/alerting system
- **Multi-region testing**: Run separate k6 instances from different geographic locations for realistic latency simulation

## Resources

- [k6 Documentation](https://k6.io/docs/)
- [k6 HTTP API](https://k6.io/docs/javascript-api/k6-http/)
- [k6 Browser Module](https://k6.io/docs/javascript-api/k6-browser/)
- [k6 Cloud](https://app.k6.io/)
