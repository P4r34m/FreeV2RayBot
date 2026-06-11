#!/usr/bin/env bash
#
# FreeV2Ray Bot — interactive installer.
# Collects the domain, bot token and admin id, generates all secrets,
# writes .env, builds and starts the Docker stack, and sets the webhook.
#
set -euo pipefail

# ----------------------------------------------------------------------------
# Helpers / colors
# ----------------------------------------------------------------------------
if [ -t 1 ]; then
  C_RESET="\033[0m"; C_BOLD="\033[1m"; C_GREEN="\033[32m"; C_RED="\033[31m"
  C_YELLOW="\033[33m"; C_BLUE="\033[36m"
else
  C_RESET=""; C_BOLD=""; C_GREEN=""; C_RED=""; C_YELLOW=""; C_BLUE=""
fi

info()  { printf "${C_BLUE}==>${C_RESET} %s\n" "$1"; }
ok()    { printf "${C_GREEN}✓${C_RESET} %s\n" "$1"; }
warn()  { printf "${C_YELLOW}!${C_RESET} %s\n" "$1"; }
err()   { printf "${C_RED}✗ %s${C_RESET}\n" "$1" >&2; }
die()   { err "$1"; exit 1; }

ENV_FILE=".env"
EXAMPLE_FILE=".env.example"

# ----------------------------------------------------------------------------
# Pre-flight checks
# ----------------------------------------------------------------------------
banner() {
  printf "${C_BOLD}"
  cat <<'EOF'

  ____            __     ______             ____        _
 |  _ \ _ __ ___ / _|   |___  /_ __ __ _   | __ )  ___ | |_
 | |_) | '__/ _ \ |_ _____ / /| '__/ _` |  |  _ \ / _ \| __|
 |  __/| | |  __/  _|_____/ /_| | | (_| |  | |_) | (_) | |_
 |_|   |_|  \___|_|      /____|_|  \__,_|  |____/ \___/ \__|

   Free V2Ray Config Telegram Bot — Installer
EOF
  printf "${C_RESET}\n"
}

require_cmd() { command -v "$1" >/dev/null 2>&1; }

detect_compose() {
  if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
  elif require_cmd docker-compose; then
    COMPOSE="docker-compose"
  else
    die "Docker Compose is not installed. Install Docker + Compose plugin and retry."
  fi
}

preflight() {
  info "Checking prerequisites..."
  require_cmd docker || die "Docker is not installed. See https://docs.docker.com/engine/install/"
  docker info >/dev/null 2>&1 || die "Docker daemon is not running (or needs sudo). Start Docker and retry."
  detect_compose
  require_cmd openssl || die "openssl is required to generate secrets. Install it and retry."
  [ -f "$EXAMPLE_FILE" ] || die "$EXAMPLE_FILE not found. Run this script from the project root."
  [ -f "docker-compose.yml" ] || die "docker-compose.yml not found. Run this script from the project root."
  ok "Docker, Compose ($COMPOSE) and openssl are available."
}

# ----------------------------------------------------------------------------
# Prompts
# ----------------------------------------------------------------------------
ask() { # ask <var> <prompt> [default]
  local __var="$1" __prompt="$2" __default="${3:-}" __input
  if [ -n "$__default" ]; then
    read -r -p "$(printf "${C_BOLD}? %s${C_RESET} [%s]: " "$__prompt" "$__default")" __input
    __input="${__input:-$__default}"
  else
    read -r -p "$(printf "${C_BOLD}? %s${C_RESET}: " "$__prompt")" __input
  fi
  printf -v "$__var" '%s' "$__input"
}

ask_required() { # loop until non-empty
  local __var="$1" __prompt="$2" __default="${3:-}"
  while :; do
    ask "$__var" "$__prompt" "$__default"
    [ -n "${!__var}" ] && break
    warn "This value is required."
  done
}

collect_input() {
  printf "\n${C_BOLD}Configuration${C_RESET}\n"
  printf "Answer the prompts below. Press Enter to accept a [default].\n\n"

  ask_required DOMAIN     "Domain pointing to this server (for TLS + webhook), e.g. bot.example.com"
  # strip any scheme/trailing slash the user may paste
  DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%/}"

  ask_required BOT_TOKEN  "Telegram bot token (from @BotFather)"
  case "$BOT_TOKEN" in
    *:*) : ;;
    *) warn "That doesn't look like a bot token (expected NNNNN:AAAA...). Continuing anyway." ;;
  esac

  ask BOT_USERNAME "Bot username without @ (used for referral links)" ""
  BOT_USERNAME="${BOT_USERNAME#@}"

  ask_required ADMIN_IDS  "Admin numeric Telegram id(s), comma-separated"

  ask ADMIN_EMAIL    "Web panel admin email" "admin@${DOMAIN}"
  ask ADMIN_PASSWORD "Web panel admin password (Enter to auto-generate)" ""
  if [ -z "$ADMIN_PASSWORD" ]; then
    ADMIN_PASSWORD="$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-14)"
    GENERATED_PASSWORD=1
  fi

  printf "\n${C_BOLD}Review${C_RESET}\n"
  printf "  Domain ............ %s\n" "$DOMAIN"
  printf "  Bot token ......... %s...\n" "${BOT_TOKEN%%:*}:****"
  printf "  Bot username ...... %s\n" "${BOT_USERNAME:-<none>}"
  printf "  Admin id(s) ....... %s\n" "$ADMIN_IDS"
  printf "  Panel email ....... %s\n" "$ADMIN_EMAIL"
  printf "\n"
  ask CONFIRM "Proceed with these settings? (y/N)" "N"
  case "$CONFIRM" in
    [yY]|[yY][eE][sS]) : ;;
    *) die "Aborted by user." ;;
  esac
}

# ----------------------------------------------------------------------------
# .env generation
# ----------------------------------------------------------------------------
set_env() { # set_env <key> <value>
  local key="$1" value="$2" esc
  esc=$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g')
  if grep -qE "^${key}=" "$ENV_FILE"; then
    sed -i.bak -E "s|^${key}=.*|${key}=${esc}|" "$ENV_FILE" && rm -f "${ENV_FILE}.bak"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

write_env() {
  if [ -f "$ENV_FILE" ]; then
    local backup=".env.backup.$(date +%s)"
    cp "$ENV_FILE" "$backup"
    warn "Existing .env backed up to $backup"
  fi
  cp "$EXAMPLE_FILE" "$ENV_FILE"

  info "Generating secrets..."
  local app_key webhook_secret db_pass db_root
  app_key="base64:$(openssl rand -base64 32)"
  webhook_secret="$(openssl rand -hex 24)"
  db_pass="$(openssl rand -hex 16)"
  db_root="$(openssl rand -hex 16)"

  set_env APP_KEY                "$app_key"
  set_env APP_ENV                "production"
  set_env APP_DEBUG              "false"
  set_env APP_URL                "https://${DOMAIN}"
  set_env SERVER_NAME            "$DOMAIN"

  set_env TELEGRAM_TOKEN         "$BOT_TOKEN"
  set_env TELEGRAM_BOT_USERNAME  "$BOT_USERNAME"
  set_env TELEGRAM_ADMIN_IDS     "$ADMIN_IDS"
  set_env TELEGRAM_WEBHOOK_SECRET "$webhook_secret"

  set_env DB_PASSWORD            "$db_pass"
  set_env DB_ROOT_PASSWORD       "$db_root"

  set_env ADMIN_EMAIL            "$ADMIN_EMAIL"
  set_env ADMIN_PASSWORD         "$ADMIN_PASSWORD"

  ok ".env written."
}

# ----------------------------------------------------------------------------
# Build / start / webhook
# ----------------------------------------------------------------------------
start_stack() {
  info "Building images and starting services (this can take a few minutes)..."
  $COMPOSE up -d --build
  ok "Containers are up. Database migrations and seeding ran via the init service."
}

set_webhook() {
  info "Registering the Telegram webhook..."
  # web depends on init (completed), so the app is migrated by now.
  if $COMPOSE exec -T web php artisan bot:set-webhook; then
    ok "Webhook registered."
  else
    warn "Could not set the webhook automatically. After DNS/TLS is ready, run:"
    printf "    %s exec web php artisan bot:set-webhook\n" "$COMPOSE"
  fi
}

summary() {
  printf "\n${C_GREEN}${C_BOLD}Installation complete!${C_RESET}\n\n"
  printf "  ${C_BOLD}Telegram bot${C_RESET} : open it and send /start\n"
  printf "  ${C_BOLD}Web panel${C_RESET}    : https://%s/admin\n" "$DOMAIN"
  printf "    email    : %s\n" "$ADMIN_EMAIL"
  if [ "${GENERATED_PASSWORD:-0}" = "1" ]; then
    printf "    password : ${C_YELLOW}%s${C_RESET}  (auto-generated — save it now)\n" "$ADMIN_PASSWORD"
  else
    printf "    password : (the one you entered)\n"
  fi
  printf "\n  ${C_BOLD}Next steps${C_RESET}\n"
  printf "    1. Ensure DNS A record for %s points to this server.\n" "$DOMAIN"
  printf "    2. Ports 80 and 443 must be open (Caddy issues TLS automatically).\n"
  printf "    3. In the web panel: add a panel (3x-ui / PasarGuard / Remnawave), set a Plan, configure referral.\n"
  printf "    4. To make the bot admin in your force-join channels for full stats.\n\n"
  printf "  Logs:    %s logs -f web\n" "$COMPOSE"
  printf "  Stop:    %s down\n" "$COMPOSE"
  printf "  Update:  git pull && %s up -d --build\n\n" "$COMPOSE"
}

main() {
  banner
  preflight
  collect_input
  write_env
  start_stack
  set_webhook
  summary
}

main "$@"
