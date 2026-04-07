// @ts-check
const { test, expect } = require('@playwright/test');
const { BASE } = require('./base-url');
const VOLUNTEER = { email: 'e2e.volunteer@test.local', password: 'Test1234!' };
const ADMIN     = { email: 'e2e.admin@test.local',     password: 'Test1234!' };

test.describe('Authentication flows', () => {

  // Clear rate limit before each test so login attempts don't get blocked
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/tests/e2e/fixtures/clear_rate_limit.php`);
  });

  test('login page loads with all required fields', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await expect(page).toHaveTitle(/Kyçu/i);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#email', VOLUNTEER.email);
    await page.fill('#password', 'WrongPassword1!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/error=wrong_credentials/);
    await expect(page.locator('.auth-alert--error')).toBeVisible();
  });

  test('login with empty password field shows error', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#email', VOLUNTEER.email);
    // Remove required so browser allows submission
    await page.evaluate(() => {
      const passwordInput = document.querySelector('input#password');
      if (passwordInput instanceof HTMLInputElement) {
        passwordInput.removeAttribute('required');
      }
    });
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/error=empty_fields/);
  });

  test('volunteer login redirects to volunteer_panel.php', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#email', VOLUNTEER.email);
    await page.fill('#password', VOLUNTEER.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/volunteer_panel\.php/);
  });

  test('admin login redirects to dashboard.php', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#email', ADMIN.email);
    await page.fill('#password', ADMIN.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard\.php/);
  });

  test('logout clears session and redirects to login', async ({ page }) => {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#email', VOLUNTEER.email);
    await page.fill('#password', VOLUNTEER.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/volunteer_panel\.php/);
    await page.waitForSelector('#header-user-menu form[action*="logout.php"]', { state: 'attached' });

    // Submit logout form directly (bypasses dropdown visibility issues)
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.locator('#header-user-menu form[action*="logout.php"]').evaluate((form) => {
        if (form instanceof HTMLFormElement) {
          form.submit();
        }
      }),
    ]);
    await expect(page).toHaveURL(/login\.php/);
  });

  test('registration page loads with required fields', async ({ page }) => {
    await page.goto(`${BASE}/views/register.php`);
    await expect(page.locator('#emri')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#confirm_password')).toBeVisible();
    await expect(page.locator('input[name="privacy_consent"]')).toBeVisible();
  });

});
