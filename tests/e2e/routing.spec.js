// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { BASE } = require('./base-url');
const VOLUNTEER = { email: 'e2e.volunteer@test.local', password: 'Test1234!' };
const ADMIN     = { email: 'e2e.admin@test.local',     password: 'Test1234!' };

test.describe('Unauthenticated access guards', () => {

  test('accessing dashboard without login redirects to login', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await expect(page).toHaveURL(/login\.php/);
  });

  test('accessing volunteer_panel without login redirects to login', async ({ page }) => {
    await page.goto(`${BASE}/views/volunteer_panel.php`);
    await expect(page).toHaveURL(/login\.php/);
  });

  test('events page is publicly accessible', async ({ page }) => {
    const response = await page.goto(`${BASE}/views/events.php`);
    if (!response) {
      throw new Error('Events page request did not return a response.');
    }
    expect(response.status()).toBe(200);
    await expect(page).not.toHaveURL(/login\.php/);
  });

  test('map page is publicly accessible', async ({ page }) => {
    const response = await page.goto(`${BASE}/views/map.php`);
    if (!response) {
      throw new Error('Map page request did not return a response.');
    }
    expect(response.status()).toBe(200);
    await expect(page).not.toHaveURL(/login\.php/);
  });

  test('help_requests page is publicly accessible', async ({ page }) => {
    const response = await page.goto(`${BASE}/views/help_requests.php`);
    if (!response) {
      throw new Error('Help requests page request did not return a response.');
    }
    expect(response.status()).toBe(200);
    await expect(page).not.toHaveURL(/login\.php/);
  });

});

// These tests use the pre-authenticated sessions saved during global-setup
test.describe('Role-based routing — volunteer (pre-authenticated)', () => {

  test.use({ storageState: path.join(__dirname, '.auth/volunteer.json') });

  test('volunteer is redirected away from dashboard to volunteer_panel', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await expect(page).toHaveURL(/volunteer_panel\.php/);
  });

  test('volunteer can access volunteer_panel', async ({ page }) => {
    await page.goto(`${BASE}/views/volunteer_panel.php`);
    await expect(page).toHaveURL(/volunteer_panel\.php/);
    await expect(page.locator('main')).toBeVisible();
  });

});

test.describe('Role-based routing — admin (pre-authenticated)', () => {

  test.use({ storageState: path.join(__dirname, '.auth/admin.json') });

  test('admin is redirected away from volunteer_panel to dashboard', async ({ page }) => {
    await page.goto(`${BASE}/views/volunteer_panel.php`);
    await expect(page).toHaveURL(/dashboard\.php/);
  });

  test('admin can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await expect(page).toHaveURL(/dashboard\.php/);
    await expect(page.locator('#db-main')).toBeVisible();
  });

});
