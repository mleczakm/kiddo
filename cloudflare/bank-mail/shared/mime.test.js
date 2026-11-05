import test from 'node:test';
import assert from 'node:assert/strict';
import { signedMultipartSource, mailForMimeParsing } from './mime.js';

const boundary = '----=_Part_Test.123';
const body = `--${boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nZażółć gęślą jaźń\r\n--${boundary}--\r\n`;
const headers = 'From: powiadomienia@alior.pl\r\nTo: test@example.test\r\nSubject: Uznanie rachunku kwotą 1 PLN\r\nMessage-ID: <test@example.test>\r\n';
const raw = type => Buffer.from(headers + type + '\r\n\r\n' + body);

test('multipart interpretation comes from body bytes, independent of unsigned outer MIME fields', () => {
 const first = signedMultipartSource(raw(`Content-Type: multipart/signed; boundary="${boundary}"`));
 for (const type of ['Content-Type: text/plain; charset=iso-8859-2', 'Content-Type: multipart/mixed; boundary="other"\r\nContent-Transfer-Encoding: base64']) {
  assert.deepEqual(signedMultipartSource(raw(type)), first);
  const normalized = Buffer.from(mailForMimeParsing(raw(type)));
  assert.ok(normalized.includes(Buffer.from(headers)));
  assert.ok(normalized.includes(Buffer.from(body)));
  assert.equal(normalized.includes(Buffer.from('Content-Transfer-Encoding: base64')), false);
 }
});

test('folded outer MIME fields are removed without changing UTF-8 identity or body bytes', () => {
 const original = raw(`Content-Type: multipart/signed;\r\n boundary="wrong"\r\nMIME-Version: 1.0\r\nContent-Transfer-Encoding: base64`);
 const normalized = Buffer.from(mailForMimeParsing(original)).toString();
 assert.ok(normalized.includes(headers));assert.ok(normalized.endsWith(body));
 assert.equal(normalized.includes('wrong'), false);
 assert.equal((normalized.match(/^Content-Type:/gm)||[]).length, 2);
 assert.equal((normalized.match(/^MIME-Version:/gm)||[]).length, 1);
});

test('plain messages and malformed or incomplete multipart bodies are not inferred', () => {
 const plain=Buffer.from(headers+'Content-Type: text/plain\r\n\r\nhello');
 assert.equal(signedMultipartSource(plain),null);
 assert.equal(mailForMimeParsing(plain),plain);
 const incomplete=Buffer.from(headers+'\r\n--boundary\r\nContent-Type: text/plain\r\n\r\nhello');
 assert.equal(signedMultipartSource(incomplete),null);
});
