# Load Testing Setup & Workflow Guide

Complete guide to setting up and running load tests for Mediscan staging environment.

## Table of Contents

0. [⚠️ Single-VPS Warning (Read This First)](#single-vps-warning-read-this-first)
1. [Initial Setup](#initial-setup)
2. [Environment Configuration](#environment-configuration)
3. [Pre-Test Requirements](#pre-test-requirements)
4. [Workflow: Running Tests Locally](#workflow-running-tests-locally)
5. [Workflow: Running Tests via GitHub Actions](#workflow-running-tests-via-github-actions)
6. [Workflow: Running Tests in k6 Cloud](#workflow-running-tests-in-k6-cloud)
7. [Monitoring During Tests](#monitoring-during-tests)
8. [Interpreting Results](#interpreting-results)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## Single-VPS Warning (Read This First)

Staging and production run as **two separate Docker Compose projects on the same physical VPS**:

```
~/mediscan/staging/     → docker-compose.staging.yml  (name: mediscan-staging)
~/mediscan/production/  → docker-compose.production.yml (name: mediscan-production)
```

They do **not** have isolated CPU/RAM — `mem_limit:` on each service caps individual containers, but there's no CPU quota, and disk I/O and network bandwidth are fully shared. This means:

- **A heavy staging load test can degrade production for real users**, especially disk I/O (both share the same Postgres/Redis disk) and network bandwidth (nginx on both terminates through the same host NIC).
- The Postgres container for staging is separate from production (production uses Supabase via `DB_URL`, per the compose file comment), so DB load is somewhat isolated — but CPU/memory/disk are not.

**Recommended safety rules:**

1. **Prefer k6 Cloud or GitHub Actions as the traffic origin** (not your local machine) — this doesn't change the VPS-sharing risk, but keeps *your* machine free to watch Grafana without competing for your own bandwidth.
2. **Run load tests during low-traffic hours** for production (check Grafana for your actual quiet period).
3. **Start small and watch `cadvisor`/`node-exporter` dashboards in Grafana for the production containers while the staging test runs** — if production's CPU/memory/disk graphs move noticeably, stop the test.
4. **Never run the "browser" scenario and the full 200-VU API scenario simultaneously** on a single small VPS — combine them only after you've confirmed headroom.
5. If you eventually need true isolation, the cheapest fix is a **second, small temporary VPS** just for running k6 from (not for hosting a copy of the app) — that only isolates the *traffic generator*, not the shared app host, but at least your test client's own resource usage won't blend into the app-host metrics.

---

## Initial Setup

### 1. Install k6

**Linux/WSL:**
```bash
# Add k6 repo (Debian/Ubuntu)
gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6-stable.list
sudo apt-get update
sudo apt-get install k6
```

**macOS:**
```bash
brew install k6
```

**Windows:**
```powershell
choco install k6
# Or download from https://github.com/grafana/k6/releases
```

**Verify installation:**
```bash
k6 version
```

### 2. Clone Repository & Navigate to Loadtest Directory

```bash
cd mediscan
cd loadtest
```

### 3. Verify Directory Structure

```bash
tree -L 2 loadtest/
# Expected output:
# loadtest/
# ├── .gitignore
# ├── README.md
# ├── SETUP_AND_WORKFLOW.md
# ├── fixtures/
# │   └── README.md
# └── k6/
#     ├── .env.example
#     ├── browser.js
#     └── scenarios.js
```

---

## Environment Configuration

### Local Testing Configuration

**Step 1: Create local environment file**

```bash
cp loadtest/k6/.env.example loadtest/k6/.env.local
```

**Step 2: Edit the configuration**

```bash
nano loadtest/k6/.env.local
# or vim, or your editor of choice
```

**Step 3: Update with your staging details**

```env
# Staging environment configuration for k6 load tests

# Base URL for the staging environment
K6_BASE_URL=https://staging.mediscan.cloud

# Test user credentials - defaults to the account already seeded by
# database/seeders/DatabaseSeeder.php (plaintext in source, not a secret).
# Not guaranteed to exist on staging automatically - see "Test User Account"
# above for the check/seed steps. Only override if using a different account.
K6_TEST_USER_EMAIL=test@example.com
K6_TEST_USER_PASSWORD=password

# Optional: k6 Cloud token (for running tests in the cloud)
# K6_CLOUD_TOKEN=

# Optional: Maximum number of VUs (limit memory usage)
# K6_MAX_VUS=300
```

### GitHub Actions Configuration

**Step 1: Add repository secrets**

Go to: Repository Settings → Secrets and variables → Actions

These are all **optional** — `load-test.yml` already defaults to `https://staging.mediscan.cloud` and the `test@example.com` seeded account. Only add secrets if you need to override them:
- `STAGING_BASE_URL` — Override the staging environment URL
- `STAGING_TEST_USER_EMAIL` — Override the test user email
- `STAGING_TEST_USER_PASSWORD` — Override the test user password

**Step 2: Verify GitHub Actions is enabled**

Go to: Repository Settings → Actions → General

Ensure:
- ✓ "Allow all actions and reusable workflows" is selected
- ✓ "Workflow permissions" has "Read and write permissions"

---

## Pre-Test Requirements

### 1. Staging Environment Accessibility

- ✓ Staging URL is reachable from your network (or CI/CD)
- ✓ SSL certificate is valid (or test from k6 Cloud to bypass local cert issues)
- ✓ No IP whitelist blocking your test traffic

### 2. Test User Account

> **⚠️ You have one VPS running both staging and production as separate Docker Compose projects.**
> They share the same physical CPU/RAM/disk. See the [Single-VPS Warning](#single-vps-warning-read-this-first) section below before running anything beyond a dry-run.

Staging is deployed by `.github/workflows/deploy.yml` via SSH to `~/mediscan/staging/` on the VPS (see `REMOTE_DIR` in that workflow). That directory contains only the rendered `docker-compose.yml` and `.env` — there is no app source checked out there, so you run commands **through the containers**, not `php artisan` directly on the host.

**Use the existing seeded account instead of creating a new one.** `database/seeders/DatabaseSeeder.php` already defines:

```php
email: test@example.com
password: password
role: Admin
email_verified_at: pre-verified
```

This is checked into the repo in plaintext — it's a seed fixture, not a real secret, so there's nothing to protect by generating a fresh load-test user.

**Important caveat:** `deploy.yml` only runs
```bash
docker compose run --rm app php artisan db:seed --class=RoleAndPermissionSeeder --force
```
on every deploy — it does **not** run the full `DatabaseSeeder`, so `test@example.com` is **not guaranteed to exist on staging** unless someone ran the full seed manually at some point. Check first:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://staging.mediscan.cloud/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password"}'
# 200 = account exists and works, go straight to running tests
# 401/422 = account doesn't exist yet on staging, seed it below
```

**If it doesn't exist yet, seed it once:**

```bash
ssh <ssh_user>@<ssh_host>   # same secrets.SSH_USER / secrets.SSH_HOST used by deploy.yml
cd ~/mediscan/staging
docker compose run --rm app php artisan db:seed --force
```

This re-runs the full `DatabaseSeeder` (idempotent — uses `firstOrCreate`), so it's safe to run even if some seeders already ran.

**Note on using an Admin account for load testing:** `test@example.com` has the `Admin` role, which is broader access than a normal end user — fine for exercising the full API surface (including `verified`-gated routes like `/professional-applications` and `/medical-information`), but be aware the account can also reach `/admin/*` web routes if you ever extend the browser scenario there.

### 3. Monitor Access

Ensure you can access Grafana during tests:
- Grafana: `https://monitorstaging.mediscan.cloud`

**You cannot and should not try to browse `/prometheus` directly** — `infrastructure/docker/nginx/templates/app.conf.template` has a hard block:
```nginx
location ^~ /prometheus { access_log off; return 404; }
```
nginx returns 404 for that path on the public hostname *before* the request ever reaches Laravel — this is intentional, not a misconfiguration. Only the Laravel `app` middleware's `AllowIps` check controls the same path, but it's irrelevant here since nginx never forwards the request. The Prometheus **server itself** (a separate container) is on the `internal` Docker network only and scrapes `app:8000` directly, bypassing nginx entirely — there is no public path to it. **Grafana is the only intended way to view these metrics**, since it queries Prometheus over the internal network and is the only piece of this stack exposed publicly (via `monitorstaging.mediscan.cloud`).

Since staging and production share the VPS, also watch the **production** row of any host-wide dashboards (`node-exporter`, `cadvisor`) in Grafana during the test — see the [Single-VPS Warning](#single-vps-warning-read-this-first).

---

## Workflow: Running Tests Locally

Best for: Development, debugging, small-scale testing, quick validation.

### Quick Start (30 seconds)

```bash
# 1. Load environment
source loadtest/k6/.env.local

# 2. Dry-run: Test with 1 VU, 1 iteration (confirms endpoints work)
k6 run --vus 1 --iterations 1 loadtest/k6/scenarios.js

# Expected output:
# ✓ login status 200
# ✓ get /me status 200
# ...
# Check passed: 95%
```

### Standard Test Run (5 minutes)

```bash
source loadtest/k6/.env.local

# Run the full API scenario
k6 run loadtest/k6/scenarios.js

# This will:
# 1. Ramp VUs 0 → 50 → 200 → 100 → 0 over ~4 minutes
# 2. Each VU authenticates once, then loops through endpoints
# 3. Print results summary at the end
```

### Advanced: Save Results to File

```bash
source loadtest/k6/.env.local

# JSON output (for analysis/graphing)
k6 run --out json=results-$(date +%s).json loadtest/k6/scenarios.js

# CSV output (for spreadsheets)
k6 run --out csv=results.csv loadtest/k6/scenarios.js

# Both formats
k6 run --out json=results.json --out csv=results.csv loadtest/k6/scenarios.js
```

### Browser Test (Local)

```bash
source loadtest/k6/.env.local

# Requires k6-browser (may need additional setup)
k6 run loadtest/k6/browser.js

# If you get "browser not found" error, see Troubleshooting section
```

### Custom VU/Duration Override

```bash
source loadtest/k6/.env.local

# Override the scenario config
k6 run --vus 100 --duration 3m loadtest/k6/scenarios.js

# This ignores the ramping in scenarios.js and uses 100 VUs for 3 minutes constant
```

### Step-by-Step Workflow: First Time Setup

**Step 1: Validate connectivity**
```bash
source loadtest/k6/.env.local
curl -s $K6_BASE_URL | head -20
# Should see HTML or a redirect, not connection error
```

**Step 2: Validate credentials**
```bash
curl -X POST $K6_BASE_URL/api/v1/login \
  -H "Content-Type: application/json" \
  -d "{\"email\": \"$K6_TEST_USER_EMAIL\", \"password\": \"$K6_TEST_USER_PASSWORD\"}"
# Should return a token, not a login error
```

**Step 3: Dry-run the scenario**
```bash
k6 run --vus 1 --iterations 1 loadtest/k6/scenarios.js
# Watch for failures; debug any 4xx/5xx responses
```

**Step 4: Small ramp test (watch Grafana)**
```bash
# In one terminal:
k6 run --vus 10 --duration 1m loadtest/k6/scenarios.js

# In another terminal/browser:
# Open Grafana and watch: CPU, memory, DB connections, request latency
```

**Step 5: Full scenario**
```bash
# Once small ramp passes, run the full load profile
k6 run loadtest/k6/scenarios.js
```

---

## Workflow: Running Tests via GitHub Actions

Best for: CI/CD integration, scheduled runs, shareable results, team visibility.

### Prerequisite: Add GitHub Secrets

See [GitHub Actions Configuration](#github-actions-configuration) above.

### Workflow 1: Manual Trigger (One-Off Test)

**Step 1: Navigate to GitHub**
```
Repository → Actions → "Staging Load Test"
```

**Step 2: Click "Run workflow"**
```
Branch: main (or your feature branch)
Test type: (choose "api", "browser", or "both")
```

**Step 3: Monitor the run**
```
Workflow will take ~5-10 minutes
Watch the logs in real-time
```

**Step 4: Download results**
```
After completion:
Click "Artifacts"
Download "load-test-results" ZIP
Extract and review results-*.json
```

### Workflow 2: Scheduled Daily Tests

Add this to `.github/workflows/load-test.yml` (or create a new scheduled workflow):

```yaml
name: Scheduled Staging Load Test

on:
  schedule:
    - cron: '0 2 * * MON'  # Every Monday at 2 AM UTC

jobs:
  load-test:
    uses: ./.github/workflows/load-test.yml
    with:
      test_type: both  # Run both API and browser tests
    secrets: inherit
```

Then add the file to version control:
```bash
git add .github/workflows/scheduled-load-test.yml
git commit -m "chore: add scheduled weekly load tests"
git push
```

### Workflow 3: Run After Deployment

Integrate load testing into your deployment pipeline:

```yaml
# In your deployment workflow, after deployment succeeds:
- name: Run load test
  if: success()
  uses: actions/workflow_run@v2
  with:
    workflow: load-test.yml
    inputs:
      test_type: 'api'
```

### Interpreting GitHub Actions Output

**Workflow Run Page:**
- `Logs` tab → Real-time k6 output
- `Artifacts` tab → results-api.json, results-browser.json (download for analysis)
- Summary shows PASS/FAIL based on thresholds

**Expected output on success:**
```
✓ load-test completed successfully
```

---

## Workflow: Running Tests in k6 Cloud

Best for: Accurate distributed load testing, removing local-network bottlenecks, hosted dashboards, trend analysis.

### Setup k6 Cloud Account

**Step 1: Create free account**
```
Visit: https://app.k6.io/
Sign up with email or GitHub
```

**Step 2: Get API token**
```
Account Settings → API Tokens → Create token
Copy the token
```

### Run from Local Machine

**Step 1: Add token to local environment**
```bash
# Edit loadtest/k6/.env.local and add:
export K6_CLOUD_TOKEN=your_token_here
```

**Step 2: Run test in cloud**
```bash
source loadtest/k6/.env.local

k6 cloud loadtest/k6/scenarios.js

# Output:
# Web Dashboard: https://app.k6.io/runs/12345
# Generating detailed report...
```

**Step 3: Watch live dashboard**
```
Click the "Web Dashboard" link
See real-time graphs of:
- Request rate
- Error rate
- Latency (p50, p95, p99)
- VU ramp
```

**Step 4: Download report after test**
```
Dashboard → Export → Download as PDF/JSON
```

### Run from GitHub Actions with k6 Cloud

**Step 1: Add token to GitHub secrets**
```
Settings → Secrets and variables → Actions
Add: K6_CLOUD_TOKEN = your_token_here
```

**Step 2: Modify workflow to use cloud**

Edit `.github/workflows/load-test.yml` and change the run commands:
```yaml
- name: Run API load test (k6 Cloud)
  if: ${{ inputs.test_type == 'api' || inputs.test_type == 'both' }}
  env:
    K6_BASE_URL: ${{ secrets.STAGING_BASE_URL }}
    K6_TEST_USER_EMAIL: ${{ secrets.STAGING_TEST_USER_EMAIL }}
    K6_TEST_USER_PASSWORD: ${{ secrets.STAGING_TEST_USER_PASSWORD }}
    K6_CLOUD_TOKEN: ${{ secrets.K6_CLOUD_TOKEN }}
  run: k6 cloud loadtest/k6/scenarios.js
```

**Step 3: Trigger workflow**
```
Actions → "Staging Load Test" → Run workflow
```

**Step 4: Check k6 Cloud dashboard**
```
Visit https://app.k6.io/runs
Your test will appear in the list
Click to view live dashboard
```

### Comparing Test Runs (Trend Analysis)

k6 Cloud automatically tracks:
- Test history over time
- Pass/fail rate trends
- Performance degradation detection

**To compare two runs:**
```
k6 Cloud Dashboard → Runs → Select two runs → "Compare"
```

---

## Monitoring During Tests

### Essential Grafana Dashboards

**Before starting test:**
1. Open Grafana: `https://monitorstaging.mediscan.cloud`
2. Create or open a dashboard with these panels:

**Application Layer:**
- HTTP request rate (requests/sec)
- HTTP error rate (%)
- Response latency (p50, p95, p99)
- Active database connections

**Database (PostgreSQL):**
- Connection count
- Query latency (avg, p95)
- Active queries
- Cache hit rate

**Queue (Redis/Horizon):**
- Queue depth (pending jobs)
- Job completion rate
- Failed job rate
- Worker availability

**Infrastructure:**
- CPU usage (app, database, sidecar)
- Memory usage
- Disk I/O
- Network in/out

### Real-Time Monitoring Workflow

**During the test (in 2 windows/terminals):**

**Terminal 1: Run the load test**
```bash
source loadtest/k6/.env.local
k6 run loadtest/k6/scenarios.js
```

**Terminal 2 / Browser: Watch Grafana**
```
1. Open Grafana dashboard
2. Set time range to "Last 5 minutes" (auto-refresh)
3. Watch for:
   - Smooth ramp-up in request rate
   - Latency staying within thresholds
   - No spike in error rate
   - Queue depth staying reasonable
   - No database connection pool exhaustion
4. Note any unusual behavior for post-test analysis
```

### What to Watch For (Red Flags)

| Symptom | Likely Cause | Action |
|---------|--------------|--------|
| Latency spikes at specific VU count | Connection pool exhaustion | Increase pool size in app config |
| Queue depth grows unbounded | Worker capacity too low | Scale up Horizon workers |
| 5xx errors start appearing | App memory leak or crash | Check app logs; restart app |
| Database slow queries spike | N+1 queries or missing indexes | Profile DB queries; add indexes |
| Sidecar (ML/OCR) becomes bottleneck | Uploads overwhelming external service | Reduce upload scenario VUs |

---

## Interpreting Results

### k6 Output Summary

After a test completes, you'll see output like:

```
     ✓ login status 200
     ✓ login returns token
     ✓ get /me status 200
     ✓ get medical-information status 200
     ...

   █ auth_and_core_api (2m3s)
      ✓ login status 200
      └─ 8500 requests, 100% OK
      └─ avg latency: 312ms, p95: 1203ms, max: 4567ms

   █ upload_pipeline (3m2s)
      └─ 450 requests, 98% OK
      └─ avg latency: 856ms, p95: 2340ms, max: 5100ms

   ✓ PASSED threshold: http_req_failed < 5%
   ✓ PASSED threshold: http_req_duration p95 < 2000ms
   ✓ PASSED threshold: api_errors < 5%

   Aggregated metrics:
   vus_max.......: 200
   vus........... : 0
   http_req_duration: avg=412ms, p95=1523ms, max=5100ms, med=365ms, min=45ms
   http_req_failed: 1.2%
   http_reqs.....: 9000 in 5m2.3s, avg=29.8/sec
   api_errors....: 1.2%
```

### Key Metrics Explained

| Metric | Meaning | Healthy Range |
|--------|---------|----------------|
| `http_req_duration (p95)` | 95th percentile request time | < 2000ms |
| `http_req_duration (max)` | Worst-case request time | < 5000ms |
| `http_req_failed` | % of failed requests (4xx, 5xx) | < 5% |
| `api_errors` | Custom error rate tracked by script | < 5% |
| `vus_max` | Peak concurrent users achieved | Matches your target |
| `http_reqs` | Total requests in test | Expected based on VUs/duration |

### Analyzing JSON Results

Export results for deeper analysis:

```bash
source loadtest/k6/.env.local
k6 run --out json=results.json loadtest/k6/scenarios.js

# Parse with jq (if installed)
jq '.metrics | keys' results.json

# Extract latency percentiles
jq '.metrics.http_req_duration.values.p95' results.json
```

### Comparing Multiple Test Runs

If you save results from multiple runs:

```bash
# Run 1 (before optimization)
k6 run --out json=results-before.json loadtest/k6/scenarios.js

# [Make changes to staging]

# Run 2 (after optimization)
k6 run --out json=results-after.json loadtest/k6/scenarios.js

# Use jq to compare
echo "Before: " $(jq '.metrics.http_req_duration.values.p95' results-before.json)
echo "After: " $(jq '.metrics.http_req_duration.values.p95' results-after.json)
```

---

## Troubleshooting

### "Failed to connect to staging URL"

**Symptom:**
```
Error: dial tcp: connection refused
```

**Solutions:**
1. Verify URL in `.env.local`: `echo $K6_BASE_URL`
2. Test curl: `curl -I $K6_BASE_URL`
3. Check firewall/VPN (if on corporate network)
4. Verify staging containers are running:
   ```bash
   ssh <ssh_user>@<ssh_host> "cd ~/mediscan/staging && docker compose ps"
   # `app` and `nginx` should show state "running"/"healthy"
   ```

---

### "login status 200 fails (401/403)"

**Symptom:**
```
✗ login status 200
Error: 401 Unauthorized
```

**Solutions:**
1. Verify test user exists: `php artisan tinker; User::where('email', '...')->first()`
2. Verify email is verified: `$user->email_verified_at` should not be null
3. Check credentials in `.env.local`: `cat loadtest/k6/.env.local | grep K6_TEST_USER`
4. Test credentials manually:
   ```bash
   curl -X POST $K6_BASE_URL/api/v1/login \
     -H "Content-Type: application/json" \
     -d "{\"email\": \"$K6_TEST_USER_EMAIL\", \"password\": \"$K6_TEST_USER_PASSWORD\"}"
   ```

---

### "http_req_duration p95 threshold crossed"

**Symptom:**
```
✗ FAILED threshold: http_req_duration p95 < 2000ms (actual: 2456ms)
```

**Solutions:**
1. **Reduce VU load**: `k6 run --vus 100 --duration 2m` (start smaller)
2. **Check Grafana**: Are resources (CPU, DB connections) maxed out?
3. **Check app logs**: Any errors or slowdowns in staging?
4. **Identify slow endpoints**: Which requests are timing out?
   ```bash
   jq '.data[] | select(.metric == "http_req_duration") | .value' results.json | sort -n | tail -20
   ```
5. **If ML/OCR sidecar slow**: Reduce upload scenario VUs

---

### "Browser test fails or hangs"

**Symptom:**
```
timeout waiting for page to load
k6-browser not found
```

**Solutions:**
1. Browser tests are optional; API tests alone are usually sufficient
2. For local browser testing, ensure Chromium is installed:
   ```bash
   which chromium  # or google-chrome, firefox
   ```
3. If running in GitHub Actions, browser tests gracefully fall back to API-only
4. Try API test first: `k6 run loadtest/k6/scenarios.js`

---

### "GitHub Actions workflow fails"

**Symptom:**
```
Error: Unable to find action with name load-test
```

**Solutions:**
1. Ensure `.github/workflows/load-test.yml` exists and is committed
2. Check workflow syntax: `git commit --dry-run` (pre-commit hooks validate YAML)
3. These secrets are optional (the workflow has working defaults) — only check them if you added overrides: Settings → Secrets and variables → Actions
4. Check Actions is enabled: Settings → Actions → General

---

### "k6 Cloud test gives permission error"

**Symptom:**
```
Error: Cloud: failed to initialize: invalid token
```

**Solutions:**
1. Verify token in `.env.local`: `echo $K6_CLOUD_TOKEN`
2. Regenerate token: k6.io → Account Settings → API Tokens → Create new
3. For GitHub Actions, verify secret is added: Settings → Secrets → K6_CLOUD_TOKEN

---

## Best Practices

### Before Running Tests

- [ ] Staging database is fresh/cleaned (no stale test data)
- [ ] Staging app is at a known good state (recent deploy)
- [ ] Test user account exists and email is verified
- [ ] Credentials are correct and tested manually
- [ ] Grafana/monitoring dashboards are open and ready
- [ ] Notify team (don't surprise prod with traffic)

### During Tests

- [ ] Watch Grafana for resource exhaustion or errors
- [ ] Don't make changes to staging during test (affects results)
- [ ] If something looks wrong, stop the test with `Ctrl+C`
- [ ] Note any anomalies for post-analysis

### After Tests

- [ ] Review results summary (did we pass thresholds?)
- [ ] Download JSON results for archival
- [ ] Compare to baseline (previous runs)
- [ ] If thresholds failed, investigate root cause
- [ ] Clean up test data from database (optional but recommended)
- [ ] Document findings in a GitHub issue or comment

### Test Data Cleanup

After tests, remove load test data:

```bash
ssh <ssh_user>@<ssh_host>
cd ~/mediscan/staging
docker compose exec app php artisan tinker
```

Inside tinker:

```php
// test@example.com is a shared seeded fixture (Admin role), not a disposable
// load-test-only account - do NOT delete the user or bulk-delete by user_id.
// The seeder also links this admin's own record via users.medical_information_id,
// and medical_information.user_id cascadeOnDelete()s child tables, so wiping
// by user_id risks taking out that fixture record too. Instead, only clean up
// records scenarios.js actually created (title prefix "Load Test Record"):
\App\Models\MedicalInformation::where('title', 'like', 'Load Test Record%')
    ->orWhere('title', 'like', 'Updated Load Test Record%')
    ->delete();

exit
```

### Baseline & Trending

Establish a baseline by running tests regularly:

1. **Monthly baseline test**: Run full API scenario, document results
2. **Keep a spreadsheet**: Track p95 latency, error rate, throughput over time
3. **Detect regression**: If p95 latency grows 20%+, investigate changes

Example tracking:
```
Date     | p95 Latency | Error Rate | Max VUs | Notes
---------|-------------|------------|---------|-------
2024-01-01 | 234ms    | 0.8%       | 200     | Baseline
2024-02-01 | 312ms    | 1.2%       | 200     | Slight degradation after auth refactor
2024-03-01 | 195ms    | 0.5%       | 200     | Improved after query optimization
```

---

## Next Steps

1. **Complete initial setup**: Follow "Initial Setup" section
2. **Configure environment**: Follow "Environment Configuration"
3. **Pre-test checklist**: Ensure test user exists
4. **Run your first test locally**: Follow "Workflow: Running Tests Locally"
5. **Establish baseline**: Document results for future comparison
6. **Schedule regular tests**: Set up GitHub Actions scheduled workflow
7. **Integrate with monitoring**: Tie load test results to your incident response process

## Support & Resources

- **k6 Docs**: https://k6.io/docs/
- **k6 Community**: https://community.k6.io/
- **k6 Slack**: https://k6io.slack.com/
- **GitHub Issues**: File any bugs in the mediscan repo

---

## Quick Reference Cheat Sheet

```bash
# Dry-run (1 VU, 1 iteration)
k6 run --vus 1 --iterations 1 loadtest/k6/scenarios.js

# Small ramp test
k6 run --vus 50 --duration 2m loadtest/k6/scenarios.js

# Full scenario
k6 run loadtest/k6/scenarios.js

# Save results
k6 run --out json=results.json loadtest/k6/scenarios.js

# Browser test
k6 run loadtest/k6/browser.js

# Cloud test
k6 cloud loadtest/k6/scenarios.js

# Override scenario config
k6 run --vus 100 --duration 5m loadtest/k6/scenarios.js

# Load env vars
source loadtest/k6/.env.local
```
