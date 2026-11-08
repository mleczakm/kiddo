import { expect, test } from '@playwright/test';
import { DEMO, loginAs } from '../support/auth';

test.describe('passwordless login', () => {
  test('a parent signs in with the e-mailed link and sees their panel', async ({ page }) => {
    await loginAs(page, DEMO.parent);

    await page.goto('/panel');
    await expect(page).toHaveURL(/\/panel/);
    // Present in the account menu (collapsed, hence not "visible") only for signed-in users.
    await expect(page.locator('a[href="/logout"]').first()).toBeAttached();
  });

  test('a parent cannot open the admin area', async ({ page }) => {
    await loginAs(page, DEMO.parent);

    const response = await page.goto('/admin');
    expect(response?.status()).toBeGreaterThanOrEqual(300);
    await expect(page.getByText('Panel Administratora')).toHaveCount(0);
  });

  test('an administrator reaches the admin dashboard', async ({ page }) => {
    await loginAs(page, DEMO.admin);

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page).toHaveTitle(/Panel Administratora/);
  });
});
