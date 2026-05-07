#!/usr/bin/env bash
#
# Stripe Toolkit · Webhooks demo launcher
# ────────────────────────────────────────
# Starts the three processes the demo needs and multiplexes their
# output with color-coded prefixes:
#
#   stripe   stripe listen --forward-to http://localhost:8000/stripe/webhook
#   app      php artisan serve --host=0.0.0.0 --port=8000 --no-reload
#   queue    php artisan queue:work --queue=stripe-webhooks
#
# Captures the webhook signing secret from `stripe listen` and syncs
# it into `.env` so signature verification matches without manual
# copy-paste.
#
# Ctrl+C stops everything and reverts no .env changes (the captured
# secret stays — it's stable per Stripe account).
#
# Usage:
#   ./demo.sh                 # default port 8000
#   PORT=8080 ./demo.sh       # custom port

set -e
cd "$(dirname "$0")"

# ─── colors ─────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
    RED=$'\033[0;31m'
    GREEN=$'\033[0;32m'
    YELLOW=$'\033[0;33m'
    BLUE=$'\033[0;34m'
    PURPLE=$'\033[0;35m'
    CYAN=$'\033[0;36m'
    DIM=$'\033[2m'
    NC=$'\033[0m'
else
    RED= GREEN= YELLOW= BLUE= PURPLE= CYAN= DIM= NC=
fi

PORT=${PORT:-8000}
HOST=${HOST:-0.0.0.0}
STRIPE_LOG="$(mktemp -t stripe-listen.XXXXXX.log)"

# ─── prerequisites ──────────────────────────────────────────────────
have() { command -v "$1" >/dev/null 2>&1; }

err() { echo "${RED}error:${NC} $*" >&2; }
info() { echo "${BLUE}setup:${NC} $*"; }
note() { echo "${DIM}      $*${NC}"; }

have php || { err "php is not installed (need 8.2+)"; exit 1; }
have stripe || {
    err "stripe CLI is not installed."
    note "install with: https://stripe.com/docs/stripe-cli"
    exit 1
}
[[ -f .env ]] || {
    err ".env is missing. Run: cp .env.example .env && php artisan key:generate"
    exit 1
}

if ! grep -q '^STRIPE_KEY=pk_test_[a-zA-Z0-9]' .env || \
   ! grep -q '^STRIPE_SECRET=sk_test_[a-zA-Z0-9]' .env; then
    err "STRIPE_KEY / STRIPE_SECRET in .env still hold placeholders."
    note "Get keys from: https://dashboard.stripe.com/test/apikeys"
    note "Then edit .env."
    exit 1
fi

# ─── helpers ────────────────────────────────────────────────────────
prefix() {
    local color=$1 label=$2
    while IFS= read -r line; do
        printf "${color}[%-6s]${NC} %s\n" "$label" "$line"
    done
}

cleanup() {
    echo
    info "Stopping services…"
    # Kill the entire process group (children + their children).
    trap - INT TERM EXIT
    kill 0 2>/dev/null || true
    wait 2>/dev/null || true
    rm -f "$STRIPE_LOG" 2>/dev/null || true
    echo "${GREEN}[ done ]${NC} bye."
    exit 0
}
trap cleanup INT TERM EXIT

# ─── 1. start stripe listen first ───────────────────────────────────
info "Starting Stripe CLI listener…"
stripe listen --forward-to "http://localhost:$PORT/stripe/webhook" 2>&1 \
    | tee "$STRIPE_LOG" \
    | prefix "$CYAN" "stripe" &

# Wait for the secret to appear (or for an error to surface).
for _ in {1..30}; do
    if grep -q 'whsec_' "$STRIPE_LOG" 2>/dev/null; then break; fi
    if grep -qE 'FATA|ERROR.*authentication' "$STRIPE_LOG" 2>/dev/null; then
        err "Stripe CLI authentication failed. Run: stripe login"
        exit 1
    fi
    sleep 0.4
done

WHSEC=$(grep -oE 'whsec_[a-zA-Z0-9]+' "$STRIPE_LOG" | head -1)
if [[ -z "$WHSEC" ]]; then
    err "Could not capture webhook signing secret from stripe listen."
    note "Check the [stripe] output above for hints."
    exit 1
fi

# Sync into .env (idempotent — sed-i with .bak for cross-platform).
CURRENT=$(grep '^STRIPE_WEBHOOK_SECRET=' .env | head -1 | cut -d= -f2)
if [[ "$WHSEC" != "$CURRENT" ]]; then
    info "Updating STRIPE_WEBHOOK_SECRET in .env (was: ${CURRENT:0:14}…)"
    if grep -q '^STRIPE_WEBHOOK_SECRET=' .env; then
        sed -i.bak "s|^STRIPE_WEBHOOK_SECRET=.*|STRIPE_WEBHOOK_SECRET=$WHSEC|" .env
    else
        echo "STRIPE_WEBHOOK_SECRET=$WHSEC" >> .env
    fi
    rm -f .env.bak
fi

# ─── 2. start the queue worker ──────────────────────────────────────
info "Starting queue worker…"
php artisan queue:work --queue=stripe-webhooks 2>&1 \
    | prefix "$PURPLE" "queue " &

# ─── 3. start the Laravel dev server ────────────────────────────────
info "Starting Laravel app on $HOST:$PORT…"
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host="$HOST" --port="$PORT" --no-reload 2>&1 \
    | prefix "$BLUE" "app   " &

# Wait for the app to be ready.
for _ in {1..40}; do
    if curl -sf "http://127.0.0.1:$PORT/up" >/dev/null 2>&1; then break; fi
    sleep 0.25
done

# ─── ready banner ───────────────────────────────────────────────────
LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
TS_IP=$(ip -4 -o addr show tailscale0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)

echo
echo "${GREEN}━━━ ready ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo
echo "  Storefront    ${CYAN}http://localhost:$PORT${NC}"
[[ -n "$LAN_IP" ]] && echo "    LAN         ${CYAN}http://$LAN_IP:$PORT${NC}"
[[ -n "$TS_IP" ]]  && echo "    Tailscale   ${CYAN}http://$TS_IP:$PORT${NC}"
echo "  Inspector     ${CYAN}http://localhost:$PORT/stripe-webhooks-debug${NC}"
echo
echo "  ${DIM}Ctrl+C to stop all three services.${NC}"
echo "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo

# Block until any service exits (or Ctrl+C).
wait
