import { defineConfig, devices } from '@playwright/test';

const inContainer = process.env.E2E_IN_CONTAINER === '1';

export default defineConfig({
  testDir: './tests',
  // Tests share one database and mutate it (cancel a lesson, ...), so they run serially.
  // Every test restores what it changed; the suite is safe to re-run without re-seeding.
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [['list'], ['html', { open: 'never' }], ['github']]
    : [['list'], ['html', { open: 'never' }]],
  timeout: 30_000,
  expect: { timeout: 7_500 },
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:9501',
    locale: 'pl-PL',
    timezoneId: 'Europe/Warsaw',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Chromium's sandbox needs privileges a plain container does not have.
        launchOptions: { args: inContainer ? ['--no-sandbox'] : [] },
      },
    },
  ],
});
