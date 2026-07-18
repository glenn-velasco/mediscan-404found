import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const BASE_URL = __ENV.K6_BASE_URL || 'https://staging.mediscan.cloud';
// Defaults match the account seeded by database/seeders/DatabaseSeeder.php
// (plaintext in source, not a secret) - not guaranteed to exist on staging
// automatically, see loadtest/SETUP_AND_WORKFLOW.md.
const TEST_USER_EMAIL = __ENV.K6_TEST_USER_EMAIL || 'test@example.com';
const TEST_USER_PASSWORD = __ENV.K6_TEST_USER_PASSWORD || 'password';

const apiLatency = new Trend('api_latency');
const apiErrors = new Rate('api_errors');

export const options = {
  scenarios: {
    auth_and_core_api: {
      executor: 'ramping-vus',
      exec: 'coreApiScenario',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 50 },  // Ramp up to 50 VUs
        { duration: '2m', target: 200 },  // Ramp up to 200 VUs (main load)
        { duration: '1m', target: 100 },  // Scale back to 100
        { duration: '30s', target: 0 },   // Ramp down
      ],
      gracefulRampDown: '10s',
    },
    upload_pipeline: {
      executor: 'ramping-vus',
      exec: 'uploadPipelineScenario',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 5 },   // Small VU pool for upload stress
        { duration: '3m', target: 10 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.05'],           // Allow max 5% failure
    'http_req_duration': ['p(95)<2000'],        // 95th percentile under 2s
    'api_errors': ['rate<0.05'],                // Max 5% error rate
  },
};

// Runs once for the whole test run (not per-VU, not per-iteration). Every VU
// impersonates the same seeded account, and Fortify's login limiter is
// 5/min per user+IP - logging in per-VU or per-iteration blows through that
// almost instantly. One shared token, reused by every VU/iteration, avoids
// hitting the login endpoint more than once.
export function setup() {
  const loginPayload = JSON.stringify({
    email: TEST_USER_EMAIL,
    password: TEST_USER_PASSWORD,
    device_name: 'k6-load-test',
  });

  const res = http.post(`${BASE_URL}/api/v1/login`, loginPayload, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  });

  const success = check(res, {
    'setup login status 200': (r) => r.status === 200,
    'setup login returns token': (r) => r.json('data.token') !== undefined,
  });

  if (!success) {
    throw new Error(`Setup login failed: ${res.status} ${res.body}`);
  }

  return { token: res.json('data.token') };
}

function authHeaders(token) {
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}

export function coreApiScenario(data) {
  const headers = authHeaders(data.token);

  // 1. Get current user
  let res = http.get(`${BASE_URL}/api/v1/me`, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'get /me status 200': (r) => r.status === 200,
  });
  apiErrors.add(res.status !== 200);
  sleep(0.5);

  // 2. Get medical information (list)
  res = http.get(`${BASE_URL}/api/v1/medical-information`, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'get medical-information status 200': (r) => r.status === 200,
  });
  apiErrors.add(res.status !== 200);
  sleep(1);

  // 3. Post a scan (lightweight audit log write)
  const scanPayload = JSON.stringify({
    event_type: 'viewed_medical_information',
    data: { item_count: 1 },
  });
  res = http.post(`${BASE_URL}/api/v1/scans`, scanPayload, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'post scan status 200': (r) => r.status === 200 || r.status === 201,
  });
  apiErrors.add(res.status > 201);
  sleep(1);

  // 4. Post medical information
  const medicalInfoPayload = JSON.stringify({
    title: `Load Test Record ${Date.now()}`,
    description: 'Test data generated during load testing',
    category: 'test',
  });
  res = http.post(`${BASE_URL}/api/v1/medical-information`, medicalInfoPayload, { headers });
  apiLatency.add(res.timings.duration);
  const postSuccess = res.status === 201;
  check(res, {
    'post medical-information status 201': (r) => r.status === 201,
  });
  apiErrors.add(res.status !== 201);

  let medicalInfoId = null;
  if (postSuccess) {
    medicalInfoId = res.json('data.id');
  }
  sleep(1);

  // 5. Get pending sync
  res = http.get(`${BASE_URL}/api/v1/pending-sync`, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'get pending-sync status 200': (r) => r.status === 200,
  });
  apiErrors.add(res.status !== 200);
  sleep(0.5);

  // 6. Post emergency QR event
  const qrEventPayload = JSON.stringify({
    event_type: 'scanned',
    scanned_at: new Date().toISOString(),
  });
  res = http.post(`${BASE_URL}/api/v1/emergency-qr/events`, qrEventPayload, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'post emergency-qr/events status 200': (r) => r.status === 200 || r.status === 201,
  });
  apiErrors.add(res.status > 201);
  sleep(1);

  // 7. If we created a medical info record, try to retrieve it
  if (medicalInfoId) {
    res = http.get(`${BASE_URL}/api/v1/medical-information/${medicalInfoId}`, { headers });
    apiLatency.add(res.timings.duration);
    check(res, {
      'get medical-information/{id} status 200': (r) => r.status === 200,
    });
    apiErrors.add(res.status !== 200);
    sleep(0.5);

    // 8. Update the medical info record
    const updatePayload = JSON.stringify({
      title: `Updated Load Test Record ${Date.now()}`,
      description: 'Updated during load testing',
    });
    res = http.put(`${BASE_URL}/api/v1/medical-information/${medicalInfoId}`, updatePayload, { headers });
    apiLatency.add(res.timings.duration);
    check(res, {
      'put medical-information/{id} status 200': (r) => r.status === 200,
    });
    apiErrors.add(res.status !== 200);
    sleep(1);

    // 9. Delete the medical info record
    res = http.delete(`${BASE_URL}/api/v1/medical-information/${medicalInfoId}`, { headers });
    apiLatency.add(res.timings.duration);
    check(res, {
      'delete medical-information/{id} status 200': (r) => r.status === 200 || r.status === 204,
    });
    apiErrors.add(res.status > 204);
    sleep(0.5);
  }

  // Random think time between iterations
  sleep(Math.random() * 2 + 1);
}

export function uploadPipelineScenario(data) {
  // Placeholder for upload/professional-application stress test.
  // Requires verified email on the test user (test@example.com is
  // pre-verified via DatabaseSeeder). Multipart upload with fixture files
  // can be added here later - see loadtest/fixtures/README.md.
  const headers = authHeaders(data.token);

  const res = http.get(`${BASE_URL}/api/v1/professional-applications`, { headers });
  apiLatency.add(res.timings.duration);
  check(res, {
    'get professional-applications status 200': (r) => r.status === 200,
  });
  apiErrors.add(res.status !== 200);
  sleep(1);
}
