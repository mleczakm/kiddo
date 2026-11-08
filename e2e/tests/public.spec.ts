import { expect, test } from '@playwright/test';

test.describe('public site', () => {
  test('workshop catalogue lists the seeded workshops', async ({ page }) => {
    await page.goto('/warsztaty');

    await expect(page.getByRole('link', { name: /\[DEMO\] Mali odkrywcy/ }).first()).toBeVisible();
  });

  test('protected areas redirect anonymous visitors to the login form', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('#form_email')).toBeVisible();

    await page.goto('/panel');
    await expect(page).toHaveURL(/\/login/);
  });
});
