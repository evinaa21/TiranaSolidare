// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost/TiranaSolidare';

// All tests in this file run with the pre-authenticated admin session
// (storageState: 'tests/e2e/.auth/admin.json', set in playwright.config.js)
// so we navigate directly to the dashboard without needing to log in.

test.describe('Admin dashboard', () => {

  test('dashboard loads and sidebar shows all admin nav items', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await expect(page).toHaveURL(/dashboard\.php/);
    await expect(page.locator('#db-sidebar')).toBeVisible();
    await expect(page.locator('[data-panel="events"]')).toBeVisible();
    await expect(page.locator('[data-panel="users"]')).toBeVisible();
    await expect(page.locator('[data-panel="reports"]')).toBeVisible();
    await expect(page.locator('[data-panel="categories"]')).toBeVisible();
  });

  test('clicking Events nav switches to events panel', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="events"]');
    await expect(page.locator('#panel-events')).toHaveClass(/active/);
    await expect(page.locator('#admin-event-list')).toBeVisible();
  });

  test('"Krijo Event" button reveals the create event form', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="events"]');
    // Form is hidden initially
    await expect(page.locator('#create-event-wrapper')).toBeHidden();
    // Click the button
    await page.click('button:has-text("Krijo Event")');
    await expect(page.locator('#create-event-wrapper')).toBeVisible();
    await expect(page.locator('#create-event-form')).toBeVisible();
  });

  test('clicking Users nav loads user list', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="users"]');
    await expect(page.locator('#panel-users')).toHaveClass(/active/);
    await expect(page.locator('#admin-user-list')).toBeVisible();
    // Wait for AJAX to populate
    await page.waitForFunction(() => {
      const el = document.querySelector('#admin-user-list');
      return el && !el.textContent.includes('Duke ngarkuar');
    }, { timeout: 10_000 });
    await expect(page.locator('#admin-user-list')).not.toContainText('Duke ngarkuar');
  });

  test('clicking Reports nav loads reports panel', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="reports"]');
    await expect(page.locator('#panel-reports')).toHaveClass(/active/);
    await expect(page.locator('#reports-charts')).toBeVisible();
  });

  test('"Gjenero Raport" button opens the report modal', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="reports"]');
    await page.click('button:has-text("Gjenero Raport")');
    await expect(page.locator('#rpt-gen-modal-backdrop')).toBeVisible();
    // All three report type buttons should be present
    await expect(page.locator('.rpt-type-btn')).toHaveCount(3);
  });

  test('"Analiza e Eventeve" report generates without error', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="reports"]');
    await page.click('button:has-text("Gjenero Raport")');
    await expect(page.locator('#rpt-gen-modal-backdrop')).toBeVisible();

    // Intercept the API call to verify it succeeds
    const responsePromise = page.waitForResponse(
      res => res.url().includes('/api/stats.php') && res.status() === 200
    );
    await page.click('button:has-text("Analiza e Eventeve")');
    const response = await responsePromise;
    const body = await response.json();
    expect(body.success).toBe(true);
  });

  test('"Vullnetarë Aktivë" report generates without error', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="reports"]');
    await page.click('button:has-text("Gjenero Raport")');
    await expect(page.locator('#rpt-gen-modal-backdrop')).toBeVisible();

    const responsePromise = page.waitForResponse(
      res => res.url().includes('/api/stats.php') && res.status() === 200
    );
    await page.click('button:has-text("Vullnetarë Aktivë")');
    const response = await responsePromise;
    const body = await response.json();
    expect(body.success).toBe(true);
  });

  test('clicking Categories nav shows category list', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="categories"]');
    await expect(page.locator('#panel-categories')).toHaveClass(/active/);
    await expect(page.locator('#category-list')).toBeVisible();
  });

  test('export users CSV link is present', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    await page.click('[data-panel="users"]');
    const exportLink = page.locator('a[href*="export.php?type=users"]').first();
    await expect(exportLink).toBeVisible();
  });

  test('admin logout clears session', async ({ page }) => {
    await page.goto(`${BASE}/views/dashboard.php`);
    // Use the sidebar logout button (form submit)
    await page.click('.db-sidebar__logout');
    await expect(page).toHaveURL(/login\.php/);
  });

});
