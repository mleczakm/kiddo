// Provision the Worker, queues, and the exact Email Routing rule after the
// application endpoint is deployed and its secrets are available.
import { readFile, writeFile, unlink } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const workerRoot = path.join(root, "cloudflare/bank-mail");
const baseConfigPath = path.join(workerRoot, "wrangler.jsonc");
const generatedConfigPath = path.join(workerRoot, "wrangler.generated.jsonc");
const envPath = process.env.ENV_FILE_PATH || path.join(root, ".env.production");

function fail(message) {
  throw new Error(message);
}

function parseEnv(text) {
  return Object.fromEntries(text.split(/\r?\n/).filter((line) => /^[A-Z][A-Z0-9_]*=/.test(line))
    .map((line) => [line.slice(0, line.indexOf("=")), line.slice(line.indexOf("=") + 1)]));
}

const appEnv = parseEnv(await readFile(envPath, "utf8"));
const address = appEnv.BANK_MAIL_ADDRESS;
const secret = appEnv.BANK_MAIL_WEBHOOK_SECRET;
const from = process.env.BANK_MAIL_FROM || appEnv.BANK_MAIL_FROM || "powiadomienia@alior.pl";
const zoneId = process.env.CLOUDFLARE_ZONE_ID;
const token = process.env.CLOUDFLARE_WORKER_TOKEN;

if (!/^[a-f0-9]{32}@warsztatowniasensoryczna\.pl$/.test(address || "")) {
  fail("BANK_MAIL_ADDRESS must be a generated 32-hex address at warsztatowniasensoryczna.pl");
}
if (!/^[a-f0-9]{64}$/.test(secret || "")) fail("BANK_MAIL_WEBHOOK_SECRET must be a generated 64-hex key");
if (!from || !zoneId || !token) fail("Bank sender, Cloudflare zone and Worker token are required");

async function api(method, endpoint, body, allowMissing = false) {
  const response = await fetch(`https://api.cloudflare.com/client/v4${endpoint}`, {
    method,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();
  if (allowMissing && response.status === 404) return null;
  if (!response.ok || !data.success) {
    fail(`Cloudflare API ${method} ${endpoint}: ${response.status} ${JSON.stringify(data.errors || [])}`);
  }
  return data;
}

function wrangler(args, input) {
  const result = spawnSync(path.join(workerRoot, "node_modules/.bin/wrangler"),
    [...args, "--config", generatedConfigPath], {
      cwd: workerRoot,
      env: { ...process.env, CLOUDFLARE_API_TOKEN: token },
      input,
      encoding: "utf8",
      stdio: input === undefined ? "inherit" : ["pipe", "inherit", "inherit"],
    });
  if (result.status !== 0) fail(`wrangler ${args.join(" ")} failed (${result.status})`);
}

const zone = await api("GET", `/zones/${zoneId}`);
if (zone.result?.name !== "warsztatowniasensoryczna.pl") {
  fail("CLOUDFLARE_ZONE_ID does not belong to warsztatowniasensoryczna.pl");
}
const accountId = zone.result.account?.id;
if (!accountId) fail("Cloudflare zone response did not include its account ID");

// Email Routing is already active on the apex. Refuse to silently alter MX
// records or create a rule if the zone's routing state is not ready.
const routing = await api("GET", `/zones/${zoneId}/email/routing`);
if (!routing.result?.enabled || routing.result.status !== "ready") {
  fail("Email Routing is not ready on warsztatowniasensoryczna.pl");
}

const queues = await api("GET", `/accounts/${accountId}/queues`);
for (const queueName of ["kiddo-bank-mail", "kiddo-bank-mail-dlq"]) {
  if (!queues.result.some((queue) => queue.queue_name === queueName)) {
    await api("POST", `/accounts/${accountId}/queues`, { queue_name: queueName });
  }
}

const config = JSON.parse(await readFile(baseConfigPath, "utf8"));
config.account_id = accountId;
config.vars = {
  APP_URL: "https://warsztatowniasensoryczna.pl",
  BANK_MAIL_ADDRESS: address,
  BANK_MAIL_FROM: from,
};
config.addresses = [address];
try {
  await writeFile(generatedConfigPath, JSON.stringify(config, null, 2), { mode: 0o600 });
  wrangler(["deploy"]);
  wrangler(["secret", "put", "BANK_MAIL_WEBHOOK_SECRET"], secret);

  const rules = await api("GET", `/zones/${zoneId}/email/routing/rules?per_page=100`);
  const exactAddress = address.toLowerCase();
  const matching = rules.result.find((rule) => rule.matchers?.some((matcher) =>
    matcher.type === "literal" && matcher.field === "to" && matcher.value?.toLowerCase() === exactAddress));
  const actionMatches = (rule) => rule.actions?.length === 1 && rule.actions[0].type === "worker" &&
    rule.actions[0].value?.length === 1 && rule.actions[0].value[0] === config.name;

  if (!matching || !matching.enabled || !actionMatches(matching)) {
    fail("Wrangler did not enable the exact bank address rule for the Kiddo Worker");
  }
} finally {
  await unlink(generatedConfigPath).catch(() => {});
}

console.log("Kiddo bank mail Worker deployed and exact-address routing rule verified");
