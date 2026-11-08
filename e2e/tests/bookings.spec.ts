import { expect, test } from '@playwright/test';
import { DEMO, loginAs } from '../support/auth';

test.describe('bookings', () => {
  test('a parent sees their seeded reservations in the panel', async ({ page }) => {
    await loginAs(page, DEMO.parent);

    await page.goto('/panel');
    await expect(page.getByText('[DEMO]').first()).toBeVisible();
  });

  test('an administrator sees reservations and their payments', async ({ page }) => {
    await loginAs(page, DEMO.admin);

    await page.goto('/admin/rezerwacje');
    await expect(page.locator('body')).toContainText('[DEMO]');

    await page.goto('/admin/platnosci');
    await expect(page).toHaveURL(/\/admin\/platnosci/);
    await expect(page.locator('body')).not.toContainText(/Internal Server Error|Exception/);
  });
});
