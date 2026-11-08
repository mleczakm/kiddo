import type { APIRequestContext } from '@playwright/test';

const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';

interface MailpitSummary {
  ID: string;
  Created: string;
}

/**
 * Waits for the newest mail sent to `email` after `since` and returns its plain-text body.
 * Mailpit is the app's SMTP sink in dev/e2e, so this is how the passwordless login link is read.
 */
export async function latestMailText(
  request: APIRequestContext,
  email: string,
  since: Date,
): Promise<string> {
  const deadline = Date.now() + 15_000;
  while (Date.now() < deadline) {
    const list = await request.get(
      `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${email}`)}&limit=5`,
    );
    const { messages = [] } = (await list.json()) as { messages?: MailpitSummary[] };
    const fresh = messages.find((m) => new Date(m.Created) >= since);
    if (fresh) {
      const detail = await request.get(`${MAILPIT_URL}/api/v1/message/${fresh.ID}`);
      return ((await detail.json()) as { Text: string }).Text;
    }
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  throw new Error(`No mail for ${email} arrived in Mailpit within 15s`);
}
