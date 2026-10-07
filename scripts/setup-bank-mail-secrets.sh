#!/usr/bin/env bash
set -euo pipefail

# Create the one-time recipient address and HMAC key as GitHub production
# environment secrets. Existing values are preserved across reruns.
REPOSITORY="${GITHUB_REPOSITORY:-mleczakm/kiddo}"
ENVIRONMENT="production"

command -v gh >/dev/null || { echo "Install and authenticate the GitHub CLI first." >&2; exit 1; }

EXISTING="$(gh secret list -R "$REPOSITORY" --env "$ENVIRONMENT" --json name --jq '.[].name')"

if ! grep -qx BANK_MAIL_ADDRESS <<<"$EXISTING"; then
  printf '%s@warsztatowniasensoryczna.pl' "$(openssl rand -hex 16)" |
    gh secret set BANK_MAIL_ADDRESS -R "$REPOSITORY" --env "$ENVIRONMENT"
  echo "BANK_MAIL_ADDRESS generated in the GitHub production environment"
else
  echo "BANK_MAIL_ADDRESS already exists — unchanged"
fi

if ! grep -qx BANK_MAIL_WEBHOOK_SECRET <<<"$EXISTING"; then
  openssl rand -hex 32 | gh secret set BANK_MAIL_WEBHOOK_SECRET -R "$REPOSITORY" --env "$ENVIRONMENT"
  echo "BANK_MAIL_WEBHOOK_SECRET generated in the GitHub production environment"
else
  echo "BANK_MAIL_WEBHOOK_SECRET already exists — unchanged"
fi
