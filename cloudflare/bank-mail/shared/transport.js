// Shared source: infrastructure-mikrus/integrations/bank-mail-worker.
// Update vendored copies with sync.mjs, then run each project's Worker tests.
const encoder = new TextEncoder();

export async function readBankMail(message, { maxSize, sizeReason, actualSizeReason = sizeReason }) {
  if (!Number.isSafeInteger(message.rawSize) || message.rawSize < 0 || message.rawSize > maxSize) {
    message.setReject(sizeReason);
    return null;
  }
  const raw = await new Response(message.raw).arrayBuffer();
  if (raw.byteLength > maxSize) {
    message.setReject(actualSizeReason);
    return null;
  }
  return raw;
}

export async function postSignedBankMail({ url, secret, body, contentType, timestamp, timestampHeader, signatureHeader, signaturePrefix = '' }) {
  const bytes = typeof body === 'string' ? encoder.encode(body) : body instanceof ArrayBuffer ? new Uint8Array(body) : new Uint8Array(body.buffer, body.byteOffset, body.byteLength);
  const prefix = encoder.encode(timestamp + '\n');
  const data = new Uint8Array(prefix.length + bytes.byteLength);
  data.set(prefix);
  data.set(bytes, prefix.length);
  const key = await crypto.subtle.importKey('raw', encoder.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const signature = [...new Uint8Array(await crypto.subtle.sign('HMAC', key, data))].map(byte => byte.toString(16).padStart(2, '0')).join('');
  // Workers rejects redirect:"error" before sending. Never follow a redirect
  // carrying bank mail or HMAC headers to another endpoint.
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': contentType, [timestampHeader]: timestamp, [signatureHeader]: signaturePrefix + signature },
    body,
    redirect: 'manual',
    signal: AbortSignal.timeout(15000),
  });
  if (response.status !== 204) throw new Error(`BANK_WEBHOOK_HTTP_${response.status}`);
  return response;
}
