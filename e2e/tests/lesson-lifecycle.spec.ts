import { expect, test } from '@playwright/test';
import { DEMO, loginAs } from '../support/auth';

/**
 * Drives the `lesson` state machine (config/packages/workflow.yaml) through the real UI:
 * active -> cancelled -> active, with the status badge as the observable state.
 */
test('an administrator can cancel a lesson and re-open it', async ({ page }) => {
  await loginAs(page, DEMO.admin);
  await page.goto('/admin');
  await page
    .locator('a[href^="/admin/zajecia/"]', { hasText: '[DEMO] Mali odkrywcy' })
    .first()
    .click();

  const status = page.getByTestId('lesson-status');
  const toggle = page.getByTestId('lesson-toggle');
  await expect(status).toHaveAttribute('data-status', 'active');

  try {
    await toggle.click();
    await expect(status).toHaveAttribute('data-status', 'cancelled');
  } finally {
    // Leave the shared database as we found it, even when an assertion above failed.
    if ((await status.getAttribute('data-status')) === 'cancelled') {
      await toggle.click();
    }
  }

  await expect(status).toHaveAttribute('data-status', 'active');
});
