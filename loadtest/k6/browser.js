import { browser } from 'k6/browser';
import { check } from 'k6';

const BASE_URL = __ENV.K6_BASE_URL || 'https://staging.mediscan.cloud';
const TEST_USER_EMAIL = __ENV.K6_TEST_USER_EMAIL || 'test@example.com';
const TEST_USER_PASSWORD = __ENV.K6_TEST_USER_PASSWORD || 'password';

export const options = {
  scenarios: {
    browser_frontend: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 5 },   // Ramp up to 5 browser VUs
        { duration: '2m', target: 15 },   // Ramp up to 15 VUs (browser load is heavier)
        { duration: '30s', target: 0 },   // Ramp down
      ],
      gracefulRampDown: '10s',
      options: {
        browser: {
          type: 'chromium',
        },
      },
    },
  },
  thresholds: {
    'browser_web_vital_fcp': ['p(95)<3000'],     // First Contentful Paint under 3s
    'browser_web_vital_lcp': ['p(95)<4000'],     // Largest Contentful Paint under 4s
  },
};

export default async function () {
  const context = await browser.createContext();
  const page = await context.newPage();

  try {
    // 1. Load homepage
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle' });
    check(page, {
      'homepage loads': () => page.url().includes(BASE_URL),
    });
    await page.waitForTimeout(500);

    // 2. Navigate to login (if not already logged in)
    let currentUrl = page.url();
    if (!currentUrl.includes('/app/dashboard')) {
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });

      // 3. Fill in login form
      const emailInput = await page.$('input[name="email"]');
      const passwordInput = await page.$('input[name="password"]');

      if (emailInput && passwordInput) {
        await emailInput.fill(TEST_USER_EMAIL);
        await passwordInput.fill(TEST_USER_PASSWORD);

        // 4. Submit login form
        const submitBtn = await page.$('button[type="submit"]');
        if (submitBtn) {
          await submitBtn.click();
          // Wait for navigation to dashboard
          await page.waitForNavigation({ waitUntil: 'networkidle' });
        }
      }

      check(page, {
        'login successful': () => page.url().includes('/app') || page.url().includes('/dashboard'),
      });
    }

    await page.waitForTimeout(1000);

    // 5. Navigate to medical information page
    await page.goto(`${BASE_URL}/app/medical-information`, { waitUntil: 'networkidle' });
    check(page, {
      'medical-information page loads': () => page.url().includes('/medical-information'),
    });

    // 6. Check if page rendered content
    const pageContent = await page.content();
    check(page, {
      'page has content': () => pageContent.length > 100,
    });

    await page.waitForTimeout(2000);

    // 7. Try to interact with a button/form if present (e.g., add new record)
    // This is a generic check; adjust selector based on actual UI
    const addBtn = await page.$('button:has-text("Add"), button:has-text("New"), a:has-text("Add")');
    if (addBtn) {
      // Just check that the button is visible/clickable
      const isVisible = await addBtn.isVisible();
      check(page, {
        'add button is visible': () => isVisible,
      });
    }

    await page.waitForTimeout(500);

    // 8. Log out (optional, but good for cleanup)
    // This is a best-effort attempt; adjust selector as needed
    const logoutLink = await page.$('a:has-text("Logout"), button:has-text("Logout")');
    if (logoutLink) {
      await logoutLink.click();
      // Don't wait for full navigation, just a moment for the request
      await page.waitForTimeout(500);
    }

  } catch (error) {
    console.error(`Browser scenario error: ${error.message}`);
  } finally {
    await page.close();
    await context.close();
  }
}
