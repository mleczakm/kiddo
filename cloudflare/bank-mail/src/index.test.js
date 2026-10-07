import assert from "node:assert/strict";
import test from "node:test";
import { acceptEmail, deliverQueue } from "./index.js";

const address = "0123456789abcdef0123456789abcdef@warsztatowniasensoryczna.pl";
const sender = "powiadomienia@alior.pl";
const rawMail = `From: ${sender}\r\nTo: ${address}\r\nSubject: Uznanie rachunku test\r\nMessage-ID: <bank-1@alior.pl>\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<html><body>Nadawca: Test<br/>Tytuł zlecenia: KIDDO<br/>kwotą 50,00 PLN</body></html>`;

function incoming({ from = sender, to = address, mime = rawMail, rawSize = Buffer.byteLength(mime), headers, forwardError } = {}) {
  const forwards = [];
  const order = [];
  const rejected = [];
  const sent = [];
  return {
    from,
    to,
    rawSize,
    headers: headers || new Headers({ "message-id": "<bank-1@alior.pl>" }),
    raw: new ReadableStream({ start(controller) { controller.enqueue(new TextEncoder().encode(mime)); controller.close(); } }),
    async forward(recipient, headers) {
      order.push("forward");
      forwards.push({ recipient, headers });
      if (forwardError) throw forwardError;
    },
    setReject(reason) { rejected.push(reason); },
    forwards,
    rejected,
    sent,
    order,
  };
}

function environment(sent = [], order = []) {
  return {
    BANK_MAIL_ADDRESS: address,
    BANK_MAIL_FROM: sender,
    BANK_MAIL_FORWARD_TO: "warsztatownia.sensoryczna@gmail.com",
    BANK_MAIL_QUEUE: { async send(payload) { order.push("queue"); sent.push(payload); } },
    sent,
    order,
  };
}

test("accepts only the configured recipient and both bank sender representations", async () => {
  const valid = incoming();
  const env = environment([], valid.order);
  await acceptEmail(valid, env);

  assert.equal(env.sent.length, 1);
  assert.equal(env.sent[0].id, "bank-1@alior.pl");
  assert.equal(valid.forwards.length, 1);
  assert.equal(valid.forwards[0].recipient, "warsztatownia.sensoryczna@gmail.com");
  assert.equal(valid.forwards[0].headers.get("X-Kiddo-Bank-Mail-ID"), "bank-1@alior.pl");
  assert.deepEqual(valid.order, ["forward", "queue"]);

  const wrongRecipient = incoming({ to: "other@warsztatowniasensoryczna.pl" });
  await acceptEmail(wrongRecipient, environment());
  assert.deepEqual(wrongRecipient.rejected, ["Unknown recipient"]);

  const wrongEnvelopeSender = incoming({ from: "attacker@example.test" });
  await acceptEmail(wrongEnvelopeSender, environment());
  assert.deepEqual(wrongEnvelopeSender.rejected, ["Unexpected sender"]);

  const spoofedHeader = incoming({
    mime: rawMail.replace(`From: ${sender}`, "From: attacker@example.test"),
  });
  await acceptEmail(spoofedHeader, environment());
  assert.deepEqual(spoofedHeader.rejected, ["Unexpected sender"]);
});

test("does not enqueue a message when Cloudflare rejects the authenticated forward", async () => {
  const message = incoming({ forwardError: new Error("Sender authentication failed") });
  const env = environment([], message.order);

  await assert.rejects(acceptEmail(message, env), /Sender authentication failed/);
  assert.deepEqual(env.sent, []);
  assert.deepEqual(message.order, ["forward"]);
});

test("rejects mail and queue payloads above the configured size limits", async () => {
  const tooLarge = incoming({ rawSize: 1024 * 1024 + 1 });
  await acceptEmail(tooLarge, environment());
  assert.deepEqual(tooLarge.rejected, ["Bank notification too large"]);

  const longBody = `<html>${"x".repeat(100 * 1024)}</html>`;
  const oversizedPayload = incoming({
    mime: rawMail.replace("<html><body>Nadawca: Test<br/>Tytuł zlecenia: KIDDO<br/>kwotą 50,00 PLN</body></html>", longBody),
  });
  await acceptEmail(oversizedPayload, environment());
  assert.deepEqual(oversizedPayload.rejected, ["Bank notification content too large"]);
});

test("uses a stable hash when Message-ID is absent or unsafe", async () => {
  const noMessageIdMail = rawMail.replace(/Message-ID: .*\r\n/, "");
  const one = incoming({ mime: noMessageIdMail, headers: new Headers() });
  const two = incoming({ mime: noMessageIdMail, headers: new Headers() });
  const firstEnv = environment();
  const secondEnv = environment();

  await acceptEmail(one, firstEnv);
  await acceptEmail(two, secondEnv);

  assert.match(firstEnv.sent[0].id, /^sha256:[a-f0-9]{64}$/);
  assert.equal(firstEnv.sent[0].id, secondEnv.sent[0].id);
  assert.equal(one.forwards[0].headers.get("X-Kiddo-Bank-Mail-ID"), firstEnv.sent[0].id);
});

test("acknowledges successful API delivery and retries failures with backoff", async (t) => {
  const originalFetch = globalThis.fetch;
  const originalDateNow = Date.now;
  t.after(() => {
    globalThis.fetch = originalFetch;
    Date.now = originalDateNow;
  });
  Date.now = () => 1_800_000_000_000;

  let request;
  const acked = [];
  const successful = {
    body: { id: "<bank-1@alior.pl>", subject: "safe" }, attempts: 1,
    ack() { acked.push(true); }, retry() { assert.fail("successful delivery must not retry"); },
  };
  globalThis.fetch = async (url, options) => {
    request = { url, options };
    return new Response(null, { status: 204 });
  };

  await deliverQueue({ messages: [successful] }, {
    APP_URL: "https://warsztatowniasensoryczna.pl",
    BANK_MAIL_WEBHOOK_SECRET: "s".repeat(40),
  });

  assert.deepEqual(acked, [true]);
  assert.equal(request.url, "https://warsztatowniasensoryczna.pl/api/bank-mail");
  const timestamp = request.options.headers["X-Kiddo-Bank-Timestamp"];
  const expected = await crypto.subtle.importKey(
    "raw", new TextEncoder().encode("s".repeat(40)),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"],
  );
  const digest = await crypto.subtle.sign(
    "HMAC", expected, new TextEncoder().encode(`${timestamp}\n${request.options.body}`),
  );
  const hex = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
  assert.equal(request.options.headers["X-Kiddo-Bank-Signature"], `v1=${hex}`);

  const retried = [];
  globalThis.fetch = async () => new Response(null, { status: 503 });
  const failed = {
    body: { id: "retry-me" }, attempts: 3,
    ack() { assert.fail("failed delivery must not acknowledge"); },
    retry(options) { retried.push(options); },
  };
  await deliverQueue({ messages: [failed] }, {
    APP_URL: "https://warsztatowniasensoryczna.pl",
    BANK_MAIL_WEBHOOK_SECRET: "s".repeat(40),
  });
  assert.deepEqual(retried, [{ delaySeconds: 120 }]);
});
