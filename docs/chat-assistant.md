# Kiddo chat assistant (ElevenLabs + MCP + Telegram)

Natural-language control of Kiddo via an on-page chat widget, an in-app MCP tool catalog, and a Telegram bot adapter that calls the same tools.

## Architecture

1. Browser Stimulus controller opens `/api/chat/signed-url` (works for guests, parents, and admins).
2. Kiddo mints a short-lived **chat token** (guest or user) and (when configured) an ElevenLabs signed WebSocket URL for the **single shared agent** (`ELEVENLABS_AGENT_ID`).
3. ElevenLabs agent calls Kiddo MCP at `/api/mcp` via **Symfony MCP Bundle** — tools from ChatToolRegistry; role comes from the chat token (`kiddo_is_admin` / guest).
4. HTTP twins under `/api/v1/tools/*` remain for tests/Telegram/n8n.
5. Telegram webhook (`/api/telegram/webhook`) links chat IDs to users and routes slash commands to the same tool registry.

## Environment

```env
ELEVENLABS_API_KEY=
ELEVENLABS_AGENT_ID=
KIDDO_MCP_SERVICE_KEY=
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
```

Feature flag: `chat_assistant` (enabled by default in `config/packages/novaway_feature_flag.yaml`).

Run migration for Telegram linking:

```bash
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
```

## ElevenLabs setup

1. Create **one** private ConvAI agent (shared by guests, parents, and admins).
2. Define dynamic variables: `kiddo_user_id`, `kiddo_user_name`, `kiddo_user_email`, `kiddo_roles`, `kiddo_chat_token`, `kiddo_is_admin`, `kiddo_is_guest`.
3. Add Kiddo as a custom MCP server:
   - URL: `https://<your-host>/api/mcp`
   - Request header `X-Kiddo-Mcp-Key` = `KIDDO_MCP_SERVICE_KEY` (workspace secret; do **not** put the chat token here)
   - Request header `X-Kiddo-Chat-Token` = `{{kiddo_chat_token}}` (per-conversation identity)
   - Optional: ElevenLabs secret token / Bearer may also be the service key, but chat identity must stay on `X-Kiddo-Chat-Token`
4. Tool approval: auto-approve reads; require approval (or rely on tool `confirm=true`) for mutations.
5. Paste the shared system prompt from [`docs/chat-assistant-prompt.md`](chat-assistant-prompt.md).
6. For a full “configure my ElevenLabs agent + MCP” brief, use the architect metaprompt:
   [`docs/chat-assistant-elevenlabs-metaprompt.md`](chat-assistant-elevenlabs-metaprompt.md).

If ElevenLabs keys are empty, the UI still loads and returns a chat token (local/dev mode).

### Auth model (important)

| Layer | Credential | Where |
|-------|------------|--------|
| Browser → Kiddo | Session optional (guest or logged-in) | `POST /api/chat/signed-url` |
| Kiddo → ElevenLabs WS | Signed URL for `ELEVENLABS_AGENT_ID` | short-lived |
| ElevenLabs → Kiddo MCP (edge) | `X-Kiddo-Mcp-Key` / service Bearer | proves caller is the agent platform |
| ElevenLabs → Kiddo MCP (identity) | `X-Kiddo-Chat-Token={{kiddo_chat_token}}` | guest / parent / admin scope |

Chat starts without login. Guests can use public catalog tools (`user.list_upcoming_lessons`, `user.get_lesson`). Profile/booking tools return a login prompt until the user signs in and refreshes chat. `admin.*` tools succeed only when the chat token has `ROLE_ADMIN` (`kiddo_is_admin=true`).

Logged-in booking: agent must use `user.me` / `user.list_children` instead of asking for name/email/phone. `user.create_booking` returns full BLIK-to-phone instructions (phone, amount, title code, ~24h). Payment confirmation today sends **email + in-app notification** — it does **not** push a message into an open ElevenLabs conversation.

## HTTP API

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /api/chat/signed-url` | Optional session (guest token if anonymous) | Mint chat token + ElevenLabs signed URL |
| `GET /api/v1/tools` | Bearer / `X-Kiddo-Chat-Token` or session | List tools for actor |
| `POST /api/v1/tools/{name}` | Bearer / `X-Kiddo-Chat-Token` or session | Invoke tool (JSON args body) |
| `GET/POST/DELETE /api/mcp` | Service key (`X-Kiddo-Mcp-Key` or Bearer) + chat token on tool calls | Symfony MCP Bundle streamable HTTP |
| `POST /api/telegram/webhook` | Optional `X-Telegram-Bot-Api-Secret-Token` | Telegram updates |

Mutating tools require `"confirm": true` in arguments.

## Tool namespaces

- `user.*` — profile, children, discovery, booking, payments, carnets, reschedule/cancel/refund, notifications, support messages
- `admin.*` — schedule, capacity/spots, series, create lesson, bookings, transfers/payments, users, inbox, notify user (ROLE_ADMIN token only)

## Telegram

1. Create a bot with BotFather; set `TELEGRAM_BOT_TOKEN`.
2. Set webhook: `https://api.telegram.org/bot<token>/setWebhook?url=https://<host>/api/telegram/webhook&secret_token=<TELEGRAM_WEBHOOK_SECRET>`
3. User flow:
   - `/polacz email@example.com` → email with 6-digit code
   - `/kod 123456` → stores `user.telegram_chat_id`
   - `/zajecia`, `/rezerwacje`, `/karnety`, `/ja`
   - Admin: `/dzisiaj`, `/przelewy`

Full Polish NL for complex booking flows remains on the website chat (ElevenLabs). Telegram is a thin command adapter over the same tools.
