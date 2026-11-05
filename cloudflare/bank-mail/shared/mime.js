// Shared source: infrastructure-mikrus/integrations/bank-mail-worker.
const encoder = new TextEncoder();
const decoder = new TextDecoder('latin1');
const concatenate = parts => {
  const result = new Uint8Array(parts.reduce((size, part) => size + part.byteLength, 0));
  let offset = 0;
  for (const part of parts) { result.set(part, offset); offset += part.byteLength; }
  return result;
};

function multipart(raw) {
  const bytes = raw instanceof ArrayBuffer ? new Uint8Array(raw) : new Uint8Array(raw.buffer, raw.byteOffset, raw.byteLength);
  const text = decoder.decode(bytes), separator = /\r?\n\r?\n/.exec(text);
  if (!separator) return null;
  const body = bytes.subarray(separator.index + separator[0].length);
  const bodyText = decoder.decode(body);
  const boundary = bodyText.match(/^--([A-Za-z0-9'()+_,./:=?-]{1,70})[ \t]*\r?\n/)?.[1];
  if (!boundary || !bodyText.split(/\r?\n/).some(line => line.trimEnd() === `--${boundary}--`)) return null;
  return { bytes, headers: text.slice(0, separator.index), body, boundary };
}

const mimeHeaders = boundary => encoder.encode(`MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary="${boundary}"\r\n\r\n`);

// Call only after authenticating the original bytes when used as a DKIM trust
// boundary. The timestamp and identity headers must be validated separately.
export function signedMultipartSource(raw) {
  const parts = multipart(raw);
  return parts ? concatenate([mimeHeaders(parts.boundary), parts.body]) : null;
}

// Parsing-only copy for Email Workers. Keep identity/subject/Message-ID bytes,
// replace only outer MIME interpretation, and preserve every original body byte.
// HMAC fingerprints / DKIM verification must still use the original raw input.
export function mailForMimeParsing(raw) {
  const parts = multipart(raw);
  if (!parts) return raw;
  const fields = [...parts.headers.matchAll(/^([^:\s]+):/gm)];
  const kept = [];
  for (let i = 0; i < fields.length; i++) {
    if (/^(?:content-type|content-transfer-encoding|mime-version)$/i.test(fields[i][1])) continue;
    const bytes = parts.bytes.subarray(fields[i].index, fields[i + 1]?.index ?? parts.headers.length);
    kept.push(bytes);
    if (bytes.at(-1) !== 10) kept.push(encoder.encode('\r\n'));
  }
  return concatenate([...kept, mimeHeaders(parts.boundary), parts.body]).buffer;
}
