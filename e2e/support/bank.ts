import { createHmac, randomUUID } from 'node:crypto';
import { expect, type APIRequestContext } from '@playwright/test';

const ADDRESS = process.env.E2E_BANK_MAIL_ADDRESS ?? 'e2e-inbox@bank-mail.e2e.test';
const SECRET =
  process.env.E2E_BANK_MAIL_WEBHOOK_SECRET ?? 'e2e-only-webhook-secret-not-for-production-use-0123456789';
const FROM = process.env.E2E_BANK_MAIL_FROM ?? 'powiadomienia@alior.pl';

/**
 * Plays the part of the bank-notification pipeline: POSTs a signed "Uznanie rachunku" (account
 * credited) e-mail to the app's webhook - exactly what the Cloudflare mail Worker does in
 * production. The app then imports the transfer, matches the payment code in the title and runs
 * the payment state machine (pay) and everything hanging off it.
 *
 * @param amount decimal string as the bank prints it, e.g. "79,00"
 * @param title the transfer title, i.e. the payment code shown to the customer
 */
export async function sendBankTransfer(
  request: APIRequestContext,
  { amount, title, sender = 'E2E Rodzic' }: { amount: string; title: string; sender?: string },
): Promise<void> {
  const body = JSON.stringify({
    id: `e2e-${randomUUID()}@alior.pl`,
    received_at: new Date().toISOString(),
    recipient: ADDRESS,
    mail_from: FROM,
    subject: `Uznanie rachunku 91.1234 kwotą ${amount} PLN`,
    html: `<html><br/>kwotą ${amount} PLN<br/>Nadawca: ${sender}<br/>Tytuł zlecenia: ${title}<br/></html>`,
    text: 'Uznanie rachunku',
  });
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = createHmac('sha256', SECRET).update(`${timestamp}\n${body}`).digest('hex');

  const response = await request.post('/api/bank-mail', {
    data: body,
    headers: {
      'Content-Type': 'application/json',
      'X-Kiddo-Bank-Timestamp': timestamp,
      'X-Kiddo-Bank-Signature': `v1=${signature}`,
    },
  });
  expect(response.status(), `bank webhook: ${await response.text()}`).toBe(204);
}

/** "95 zł" / "79,50 zł" / "1 079,00 PLN" -> "95,00" / "79,50" / "1 079,00" (the bank always prints two decimals) */
export function bankAmount(displayed: string): string {
  const match = displayed.replace(/\u00a0/g, ' ').match(/(\d[\d ]*?)(?:[.,](\d{2}))?\s*(?:zł|PLN|$)/);
  if (!match) {
    throw new Error(`Cannot read an amount from "${displayed}"`);
  }
  return `${match[1]},${match[2] ?? '00'}`;
}
