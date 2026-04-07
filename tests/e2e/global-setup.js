// @ts-check
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { BASE } = require('./base-url');

module.exports = async function globalSetup() {
  // Ensure auth directory exists
  const authDir = path.join(__dirname, '.auth');
  if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });

  const browser = await chromium.launch();

  // ── Clear rate-limit log so test runs don't get blocked ──────────────
  const cleanCtx = await browser.newContext();
  const cleanPage = await cleanCtx.newPage();
  await cleanPage.goto(`${BASE}/tests/e2e/fixtures/clear_rate_limit.php`);
  const body = await cleanPage.textContent('body');
  console.log('[global-setup] Rate limit cleared:', body);
  await cleanCtx.close();

  // ── Log in as VOLUNTEER ───────────────────────────────────────────────
  const volunteerCtx = await browser.newContext();
  const volunteerPage = await volunteerCtx.newPage();
  await volunteerPage.goto(`${BASE}/views/login.php`);
  await volunteerPage.fill('#email', 'e2e.volunteer@test.local');
  await volunteerPage.fill('#password', 'Test1234!');
  // Wait for navigation triggered by form submit
  await Promise.all([
    volunteerPage.waitForNavigation({ timeout: 15_000 }),
    volunteerPage.click('button[type="submit"]'),
  ]);
  const volunteerFinalURL = volunteerPage.url();
  console.log('[global-setup] Volunteer landed on:', volunteerFinalURL);
  if (!volunteerFinalURL.includes('volunteer_panel')) {
    throw new Error(`Volunteer login failed — ended up at: ${volunteerFinalURL}`);
  }
  await volunteerCtx.storageState({ path: path.join(authDir, 'volunteer.json') });
  await volunteerCtx.close();

  // ── Clear rate limit again ────────────────────────────────────────────
  const clean2 = await browser.newContext();
  const clean2page = await clean2.newPage();
  await clean2page.goto(`${BASE}/tests/e2e/fixtures/clear_rate_limit.php`);
  await clean2.close();

  // ── Log in as ADMIN ────────────────────────────────────────────────────
  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();
  await adminPage.goto(`${BASE}/views/login.php`);
  await adminPage.fill('#email', 'e2e.admin@test.local');
  await adminPage.fill('#password', 'Test1234!');
  // Wait for navigation triggered by form submit
  await Promise.all([
    adminPage.waitForNavigation({ timeout: 15_000 }),
    adminPage.click('button[type="submit"]'),
  ]);
  const adminFinalURL = adminPage.url();
  console.log('[global-setup] Admin landed on:', adminFinalURL);
  if (!adminFinalURL.includes('dashboard')) {
    throw new Error(`Admin login failed — ended up at: ${adminFinalURL}`);
  }
  await adminCtx.storageState({ path: path.join(authDir, 'admin.json') });
  await adminCtx.close();

  // ── Final rate limit clear before the actual test run ─────────────────
  const clean3 = await browser.newContext();
  const clean3page = await clean3.newPage();
  await clean3page.goto(`${BASE}/tests/e2e/fixtures/clear_rate_limit.php`);
  await clean3.close();

  await browser.close();
  console.log('[global-setup] Done — auth states saved');
};
