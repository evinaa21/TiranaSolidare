// @ts-check
const { test, expect } = require('@playwright/test');
const { BASE } = require('./base-url');

test.describe('Public pages load correctly', () => {

  test('events list page loads with correct title', async ({ page }) => {
    await page.goto(`${BASE}/views/events.php`);
    await expect(page).toHaveTitle(/Evente/i);
    await expect(page.locator('main')).toBeVisible();
  });

  test('events page does not show cancelled events in the listing', async ({ page }) => {
    await page.goto(`${BASE}/views/events.php`);
    // Wait for page content
    await page.waitForSelector('main');
    // Cancelled badges should not appear on the listing page
    const cancelledBadges = page.locator('.event-card .badge--cancelled, [data-status="cancelled"]');
    await expect(cancelledBadges).toHaveCount(0);
  });

  test('map page loads with the map container', async ({ page }) => {
    await page.goto(`${BASE}/views/map.php`);
    await expect(page).toHaveTitle(/Harta/i);
    await expect(page.locator('#overview-map')).toBeVisible();
  });

  test('help requests list page loads with request grid', async ({ page }) => {
    await page.goto(`${BASE}/views/help_requests.php`);
    await expect(page).toHaveTitle(/Kërkesat/i);
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('.rq-grid')).toBeVisible();
  });

  test('leaderboard page loads', async ({ page }) => {
    const response = await page.goto(`${BASE}/views/leaderboard.php`);
    if (!response) {
      throw new Error('Leaderboard request did not return a response.');
    }
    expect(response.status()).toBe(200);
    await expect(page).not.toHaveURL(/login\.php/);
    await expect(page.locator('.lb-container')).toBeVisible();
  });

  test('404 page renders for unknown routes', async ({ page }) => {
    await page.goto(`${BASE}/views/does_not_exist.php`);
    // Either a 404 response or the 404 view
    const url = page.url();
    const body = await page.content();
    const is404 = url.includes('404') || body.includes('404') || body.includes('Faqja nuk u gjet');
    expect(is404).toBeTruthy();
  });

});
