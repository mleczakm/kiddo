// Shared source: infrastructure-mikrus/integrations/bank-mail-worker.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac, createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';
import { Miniflare, Log, LogLevel, Response } from 'miniflare';

class TestLog extends Log { error() {} }

export async function bankMailContract({ entry, compatibilityDate, mode, headerPrefix, timestampFormat, signaturePrefix = '', maxSize, central = false }) {
  const source = await build({ entryPoints: [fileURLToPath(entry)], bundle: true, write: false, format: 'esm', platform: 'browser', target: 'es2022' });
  const domain = 'bank.example.test';
  const recipient = `${mode === 'raw' ? 'wplaty+' : ''}${'a'.repeat(32)}@${domain}`;
  const sender = 'powiadomienia@alior.pl';
  const secret = 'b'.repeat(64);
  const webhook = 'https://backend.example.test/api/bank-mail';
  const payload = { id: 'bank-test', subject: 'Zażółć gęślą jaźń', body: 'test', ...(central ? { recipient } : {}) };
  const mail = (to = recipient, from = sender) => Buffer.from(`From: ${from}\r\nTo: ${to}\r\nMessage-ID: <bank-test@alior.pl>\r\nSubject: Uznanie rachunku\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nZażółć gęślą jaźń\r\n`);
  // Invoke the actual bundled handlers inside workerd. Only event callbacks,
  // the queue store and the receiving HTTP service are replaced by test doubles.
  const harness = `import worker from './worker.js';
    export default { async fetch(request, env) {
      const url = new URL(request.url), result = { acked: 0, retries: [], queued: [], rejected: [] };
      try {
        if (url.pathname === '/queue') {
          const body = await request.json();
          await worker.queue({ messages: [{ body, attempts: Number(request.headers.get('attempts') || 1), ack() { result.acked++; }, retry(options) { result.retries.push(options); } }] }, env);
        } else {
          const raw = await request.arrayBuffer();
          const message = { from: request.headers.get('mail-from'), to: request.headers.get('mail-to'), rawSize: Number(request.headers.get('raw-size') ?? raw.byteLength), raw: new Response(raw).body, headers: new Headers({ 'message-id': '<bank-test@alior.pl>' }), setReject(reason) { result.rejected.push(reason); } };
          await worker.email(message, { ...env, BANK_MAIL_QUEUE: { async send(body) { result.queued.push(body); } } });
        }
      } catch(error) { result.error = error.message; }
      return Response.json(result);
    } };`;

  function runtime(t, handler) {
    const values = { BANK_WEBHOOK_URL: webhook, BANK_WEBHOOK_SECRET: secret, NOTIFICATION_DOMAIN: domain, APP_URL: 'https://backend.example.test', BANK_MAIL_ADDRESS: recipient, BANK_MAIL_FROM: sender, BANK_MAIL_WEBHOOK_SECRET: secret };
    if (central) values.BANK_MAIL_INSTANCES = JSON.stringify({ [recipient]: { id: 'test-instance', appUrl: 'https://backend.example.test', secret, from: sender } });
    const mf = new Miniflare({
      cf: false, telemetry: { enabled: false }, log: new TestLog(LogLevel.NONE),
      workers: [{
        config: {
          name: 'bank-mail-contract', compatibilityDate,
          manifest: { mainModule: 'harness.js', modules: { 'harness.js': { type: 'esm', contents: harness }, 'worker.js': { type: 'esm', contents: source.outputFiles[0].text } } },
          env: Object.fromEntries(Object.entries(values).map(([name, value]) => [name, { type: 'text', value }])),
        },
        dev: { outboundService: { type: 'fetcher', handler } },
      }],
    });
    t.after(() => mf.dispose());
    return mf;
  }

  async function email(mf, { raw = mail(), to = recipient, from = sender, rawSize } = {}) {
    const headers = { 'mail-from': from, 'mail-to': to };
    if (rawSize !== undefined) headers['raw-size'] = String(rawSize);
    return (await mf.dispatchFetch('http://localhost/email', { method: 'POST', body: raw, headers })).json();
  }
  async function deliver(mf, attempts = 1) {
    return mode === 'raw' ? email(mf) : (await mf.dispatchFetch('http://localhost/queue', { method: 'POST', headers: { attempts: String(attempts) }, body: JSON.stringify(central ? { instanceId: 'test-instance', payload } : payload) })).json();
  }
  function failed(result, delay = 30) {
    if (mode === 'raw') assert.ok(result.error, JSON.stringify(result));
    else { assert.equal(result.acked, 0); assert.deepEqual(result.retries, [{ delaySeconds: delay }]); }
  }

  test('workerd: actual handler forwards unchanged bytes/JSON with the expected HMAC protocol', { timeout: 20000 }, async t => {
    let calls = 0;
    const mf = runtime(t, async request => {
      calls++;
      assert.equal(request.url, webhook);
      assert.equal(request.method, 'POST');
      assert.equal(request.headers.get('content-type'), mode === 'raw' ? 'message/rfc822' : 'application/json');
      const body = Buffer.from(await request.arrayBuffer());
      assert.deepEqual(body, mode === 'raw' ? mail() : Buffer.from(JSON.stringify(payload)));
      const timestamp = request.headers.get(`${headerPrefix}-Timestamp`);
      const time = timestampFormat === 'iso' ? Date.parse(timestamp) : Number(timestamp) * (timestampFormat === 'seconds' ? 1000 : 1);
      assert.ok(Math.abs(Date.now() - time) < 5000);
      assert.equal(request.headers.get(`${headerPrefix}-Signature`), signaturePrefix + createHmac('sha256', secret).update(timestamp + '\n').update(body).digest('hex'));
      return new Response(null, { status: 204 });
    });
    const result = await deliver(mf);
    assert.equal(result.error, undefined);
    if (mode === 'queue') { assert.equal(result.acked, 1); assert.deepEqual(result.retries, []); }
    assert.equal(calls, 1);
  });

  test('workerd: delivery waits for backend acknowledgement', { timeout: 20000 }, async t => {
    let arrived, acknowledge;
    const arrival = new Promise(resolve => { arrived = resolve; });
    const acknowledgement = new Promise(resolve => { acknowledge = resolve; });
    const mf = runtime(t, async () => { arrived(); await acknowledgement; return new Response(null, { status: 204 }); });
    let completed = false;
    const delivery = deliver(mf).then(result => { completed = true; return result; });
    try { await arrival; assert.equal(completed, false); } finally { acknowledge(); }
    assert.equal((await delivery).error, undefined);
  });

  for (const status of [200, 301, 302, 303, 307, 308, 401, 500, 503]) {
    test(`workerd: HTTP ${status} fails/retries delivery without following redirects`, { timeout: 20000 }, async t => {
      const urls = [];
      const mf = runtime(t, request => { urls.push(request.url); return new Response(null, { status, headers: { Location: 'https://other.example.test/leak' } }); });
      failed(await deliver(mf));
      assert.deepEqual(urls, [webhook]);
    });
  }

  test('workerd: network errors fail delivery; queue backoff remains bounded', { timeout: 20000 }, async t => {
    const mf = runtime(t, () => { throw new Error('TEST_CONNECTION_FAILURE'); });
    if (mode === 'raw') failed(await deliver(mf));
    else for (const [attempts, delay] of [[1, 30], [3, 120], [10, 1800]]) failed(await deliver(mf, attempts), delay);
  });

  test('workerd: wrong recipients never reach the backend or queue', { timeout: 20000 }, async t => {
    const mf = runtime(t, () => { assert.fail('Unexpected outbound request'); });
    for (const to of [`other@${domain}`, recipient + '.attacker.test']) {
      const result = await email(mf, { to, raw: mail(to) });
      assert.ok(result.rejected.length);
      assert.deepEqual(result.queued, []);
    }
  });

  test('workerd: advertised and actual raw size limits are both enforced', { timeout: 20000 }, async t => {
    const mf = runtime(t, () => { assert.fail('Oversized mail must not leave Worker'); });
    const oversized = Buffer.concat([mail(), Buffer.alloc(maxSize, 120)]);
    for (const options of [{ rawSize: maxSize + 1 }, { raw: oversized, rawSize: 1 }, { rawSize: -1 }]) {
      const result = await email(mf, options);
      assert.ok(result.rejected.length, JSON.stringify(result));
      assert.deepEqual(result.queued, []);
    }
  });

  if (mode === 'queue') {
    test('workerd: Alior multipart preserves content and original fingerprint despite outer MIME changes', { timeout: 20000 }, async t => {
      const mf = runtime(t, () => { assert.fail('Unexpected outbound request'); });
      const boundary='----=_Alior_Contract.123';
      const content=`--${boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nTytuł zlecenia: a 7bc\r\n--${boundary}\r\nContent-Type: application/pkcs7-signature\r\n\r\nsignature\r\n--${boundary}--\r\n`;
      for (const type of [`multipart/signed; boundary="wrong"`, 'text/plain; charset=iso-8859-2']) {
        const raw=Buffer.from(`From: ${sender}\r\nTo: ${recipient}\r\nMessage-ID: <bank-test@alior.pl>\r\nSubject: Uznanie rachunku\r\nContent-Type: ${type}\r\nContent-Transfer-Encoding: base64\r\n\r\n${content}`);
        const result=await email(mf,{raw});
        assert.deepEqual(result.rejected,[]);assert.equal(result.queued.length,1);
        const queued=central?result.queued[0].payload:result.queued[0];
        assert.equal((queued.body??queued.text).trim(),'Tytuł zlecenia: a 7bc');
        if(headerPrefix!=='X-Kiddo-Bank')assert.equal(queued.id,createHash('sha256').update(raw).digest('hex'));
      }
    });
    test('workerd: envelope and visible sender must both match', { timeout: 20000 }, async t => {
      const mf = runtime(t, () => { assert.fail('Unexpected outbound request'); });
      for (const options of [{ from: 'attacker@example.test' }, { raw: mail(recipient, 'attacker@example.test') }]) {
        const result = await email(mf, options);
        assert.deepEqual(result.rejected, ['Unexpected sender']);
        assert.deepEqual(result.queued, []);
      }
      const accepted = await email(mf);
      assert.deepEqual(accepted.rejected, []);
      assert.equal(accepted.queued.length, 1);
    });
    test('workerd: queue payload limit is preserved', { timeout: 20000 }, async t => {
      const mf = runtime(t, () => { assert.fail('Unexpected outbound request'); });
      const result = await email(mf, { raw: Buffer.concat([mail(), Buffer.alloc(100 * 1024, 120)]) });
      assert.deepEqual(result.rejected, ['Bank notification content too large']);
      assert.deepEqual(result.queued, []);
    });
  }
}
