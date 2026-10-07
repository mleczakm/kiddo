import PostalMime from "postal-mime";

const encoder = new TextEncoder();
const maxRawSize = 1024 * 1024;
const maxQueuePayloadSize = 96 * 1024;

function normalizedAddress(value) {
  return String(value || "").trim().toLowerCase();
}

async function sha256Hex(value) {
  const bytes = value instanceof ArrayBuffer ? value : encoder.encode(value);
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function bankMailId(messageId, raw) {
  const candidate = String(messageId || "").trim().replace(/^<(.+)>$/, "$1");
  if (candidate && candidate.length <= 255 && !/[\r\n\x00-\x1F\x7F]/.test(candidate)) {
    return candidate;
  }
  return `sha256:${await sha256Hex(raw)}`;
}

export async function acceptEmail(message, env) {
  const recipient = normalizedAddress(message.to);
  if (recipient !== normalizedAddress(env.BANK_MAIL_ADDRESS)) {
    message.setReject("Unknown recipient");
    return;
  }

  if (!Number.isSafeInteger(message.rawSize) || message.rawSize > maxRawSize) {
    message.setReject("Bank notification too large");
    return;
  }

  const raw = await new Response(message.raw).arrayBuffer();
  const parsed = await PostalMime.parse(raw);
  const expectedSender = normalizedAddress(env.BANK_MAIL_FROM);
  if (!expectedSender || normalizedAddress(message.from) !== expectedSender ||
      normalizedAddress(parsed.from?.address) !== expectedSender) {
    message.setReject("Unexpected sender");
    return;
  }

  const id = await bankMailId(parsed.messageId || message.headers.get("message-id"), raw);
  const payload = {
    id,
    received_at: new Date().toISOString(),
    recipient,
    mail_from: normalizedAddress(message.from),
    subject: parsed.subject || "",
    html: parsed.html || "",
    text: parsed.text || "",
  };
  const body = JSON.stringify(payload);
  if (encoder.encode(body).byteLength > maxQueuePayloadSize) {
    message.setReject("Bank notification content too large");
    return;
  }

  // No copy is kept in a mailbox: the queue is the only store. Cloudflare has
  // already required SPF or DKIM to pass for inbound mail, and the queue retries
  // delivery to the application until it confirms, then dead-letters. If it
  // cannot be queued, reject so the sender sees the failure instead of losing it.
  try {
    await env.BANK_MAIL_QUEUE.send(payload);
  } catch (error) {
    console.error("Bank notification could not be queued", id, String(error));
    message.setReject("Temporary processing failure");
  }
}

async function signature(secret, timestamp, body) {
  const key = await crypto.subtle.importKey(
    "raw", encoder.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"],
  );
  const digest = await crypto.subtle.sign("HMAC", key, encoder.encode(`${timestamp}\n${body}`));
  const hex = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
  return `v1=${hex}`;
}

export async function deliverQueue(batch, env) {
  for (const message of batch.messages) {
    const body = JSON.stringify(message.body);
    const timestamp = Math.floor(Date.now() / 1000).toString();
    try {
      const response = await fetch(`${env.APP_URL}/api/bank-mail`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Kiddo-Bank-Timestamp": timestamp,
          "X-Kiddo-Bank-Signature": await signature(env.BANK_MAIL_WEBHOOK_SECRET, timestamp, body),
        },
        body,
        signal: AbortSignal.timeout(15000),
      });
      if (!response.ok) throw new Error(`Application returned HTTP ${response.status}`);
      message.ack();
    } catch (error) {
      console.error("Bank notification delivery failed", message.body.id, String(error));
      message.retry({ delaySeconds: Math.min(1800, 30 * 2 ** Math.min(message.attempts - 1, 6)) });
    }
  }
}

export default {
  email: acceptEmail,
  queue: deliverQueue,
};
