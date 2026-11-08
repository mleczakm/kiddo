import { expect, type Page } from '@playwright/test';
import { latestMailText } from './mailpit';

/** Accounts created by `bin/console app:dev:seed-demo`. */
export const DEMO = {
  admin: 'admin@demo.kiddo.local',
  instructor: 'host@demo.kiddo.local',
  parent: 'anna@demo.kiddo.local',
} as const;

/**
 * Logs in the way a real user does: submit the e-mail on /login, read the magic link from
 * Mailpit, open it. The link's host is whatever DEFAULT_URI the app was booted with, so only
 * its path and query are used - it always hits the app under test.
 */
export async function loginAs(page: Page, email: string): Promise<void> {
  const requestedAt = new Date(Date.now() - 1_000);

  // The admin area opens a first-visit guided tour with a full-screen overlay that swallows clicks.
  await page.addInitScript(() => localStorage.setItem('kiddo_admin_tour_seen', '1'));

  await page.goto('/login');
  await page.locator('#form_email').fill(email);
  await page.locator('#form_submit').click();

  const text = await latestMailText(page.request, email, requestedAt, /\/login_check\?/);
  const link = text.match(/https?:\/\/\S*\/login_check\?\S+/)?.[0];
  expect(link, 'login mail contains a /login_check link').toBeTruthy();

  const url = new URL(link!);
  await page.goto(`${url.pathname}${url.search}`);
  await expect(page).not.toHaveURL(/\/login/);
}
