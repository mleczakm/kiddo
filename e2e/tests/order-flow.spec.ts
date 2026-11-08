import { expect, test } from '@playwright/test';
import { DEMO, loginAs } from '../support/auth';
import { bankAmount, sendBankTransfer } from '../support/bank';

const WORKSHOP = 'Bobasowe muzykowanie';

/**
 * The money path, end to end:
 *   parent books a seat  ->  gets a payment code  ->  bank "credits the account" (signed webhook)
 *   ->  transfer is imported and matched  ->  payment `pay` transition  ->  booking confirmed.
 */
test('a parent books a workshop, pays by transfer and the booking is confirmed', async ({
  page,
  request,
}) => {
  await loginAs(page, DEMO.parent);

  // Count the reservations already scheduled, so the test also works when re-run on the same data.
  await page.goto('/panel/zajecia');
  await expect(page.getByRole('heading').first()).toBeVisible();
  const scheduledBefore = await page.getByText(WORKSHOP).count();

  // 1. Open the workshop (its page opens the booking modal for the next date) and accept the terms.
  await page.goto('/warsztaty/demo-bobasowe-muzykowanie');
  await page.locator('#terms').check();
  await page.getByTestId('book-and-pay').click();

  // 2. Confirm the order with the required consents.
  await page.getByTestId('payment-terms').check();
  const withdrawal = page.getByTestId('payment-withdrawal');
  if (await withdrawal.count()) {
    await withdrawal.check();
  }
  await page.getByTestId('confirm-booking').click();

  // 3. The modal now waits for the transfer and shows the code to put in its title.
  const code = page.getByTestId('payment-code');
  await expect(code).toBeVisible();
  const paymentCode = (await code.innerText()).trim();
  expect(paymentCode).toMatch(/^[A-Z0-9]{4,}$/);
  const amount = bankAmount(await page.getByTestId('payment-amount').innerText());

  // 4. The bank confirms the transfer.
  await sendBankTransfer(request, { amount, title: paymentCode });

  // 5. The open modal polls the payment status and flips to "paid" without a reload.
  await expect(page.getByTestId('payment-paid')).toBeVisible({ timeout: 20_000 });

  // 6. The reservation is confirmed: it now shows up in the parent's schedule.
  await page.goto('/panel/zajecia');
  // (a booking renders its title more than once - list and mobile layouts - so compare, don't equate)
  await expect.poll(() => page.getByText(WORKSHOP).count()).toBeGreaterThan(scheduledBefore);
});

test('the bank webhook rejects an unsigned or mis-signed request', async ({ request }) => {
  const response = await request.post('/api/bank-mail', {
    data: '{}',
    headers: {
      'Content-Type': 'application/json',
      'X-Kiddo-Bank-Timestamp': Math.floor(Date.now() / 1000).toString(),
      'X-Kiddo-Bank-Signature': `v1=${'0'.repeat(64)}`,
    },
  });

  expect(response.status()).toBe(401);
});
