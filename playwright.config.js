// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/TiranaSolidare';

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: false,
  retries: 0,
  workers: 1,

  globalSetup: require.resolve('./tests/e2e/global-setup.js'),

  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/e2e/reports', open: 'never' }],
  ],

  use: {
    baseURL,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'on-first-retry',
    navigationTimeout: 15_000,
  },

  projects: [
    // Unauthenticated tests — no stored session
    {
      name: 'unauthenticated',
      testMatch: ['**/pages.spec.js'],
      use: { ...devices['Desktop Chrome'] },
    },
    // Auth-flow tests — no stored session (tests the login process itself)
    {
      name: 'auth-flows',
      testMatch: ['**/auth.spec.js'],
      use: { ...devices['Desktop Chrome'] },
    },
    // Routing tests — no stored session
    {
      name: 'routing',
      testMatch: ['**/routing.spec.js'],
      use: { ...devices['Desktop Chrome'] },
    },
    // Admin tests — uses pre-authenticated admin session
    {
      name: 'admin',
      testMatch: ['**/admin.spec.js'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: path.join(__dirname, 'tests/e2e/.auth/admin.json'),
      },
    },
  ],
});

