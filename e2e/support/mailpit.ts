import type { APIRequestContext } from '@playwright/test';

const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';

interface MailpitSummary {
  ID: string;
  Created: string;
}

/**
 * Waits for a mail to `email`, sent after `since`, whose plain-text body matches `pattern`, and
 * returns that body. The app sends other mails to the same demo accounts (e.g. admin notices from
 * the scheduler), so the body - not just the recipient - has to identify the one we are after.
 * Mailpit is the app's SMTP sink in dev/e2e.
 */
export async function latestMailText(
  request: APIRequestContext,
  email: string,
  since: Date,
  pattern: RegExp,
): Promise<string> {
  const deadline = Date.now() + 15_000;
  while (Date.now() < deadline) {
    const list = await request.get(
      `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${email}`)}&limit=20`,
    );
    const { messages = [] } = (await list.json()) as { messages?: MailpitSummary[] };
    for (const summary of messages.filter((m) => new Date(m.Created) >= since)) {
      const detail = await request.get(`${MAILPIT_URL}/api/v1/message/${summary.ID}`);
      const text = ((await detail.json()) as { Text: string }).Text;
      if (pattern.test(text)) {
        return text;
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  throw new Error(`No mail for ${email} matching ${pattern} arrived in Mailpit within 15s`);
}
