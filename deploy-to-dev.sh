#!/usr/bin/env bash
# SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Deploy the current working tree of the fairregister Nextcloud plugin to
# a remote Nextcloud dev/staging instance.
#
# Flow:
#   1. Build app for production (composer --no-dev + npm run build)
#   2. Pack the app folder as a tarball
#   3. scp the tarball to the remote host
#   4. docker cp it into the Nextcloud container on that host
#   5. unpack into <NC>/custom_apps/fairregister (replacing existing)
#   6. occ upgrade + app:enable
#
# Config via env vars (defaults below). Override at call time:
#
#   SSH_HOST=nc.dev.example.org REMOTE_CONTAINER=nextcloud-aio-nextcloud \
#     ./deploy-to-dev.sh
#
# Pattern borrowed from nextcloud_fairmeeting/deploy-to-dev.sh.
set -euo pipefail

SSH_HOST="${SSH_HOST:-nx-dev2}"
REMOTE_CONTAINER_FILTER="${REMOTE_CONTAINER_FILTER:-nextcloud-aio-nextcloud}"
REMOTE_CONTAINER="${REMOTE_CONTAINER:-}"
REMOTE_APP_PATH="${REMOTE_APP_PATH:-/var/www/html/custom_apps}"
SKIP_BUILD="${SKIP_BUILD:-0}"

C_GREEN='\033[0;32m'; C_YELLOW='\033[1;33m'; C_RED='\033[0;31m'; C_RESET='\033[0m'
log()  { printf "${C_GREEN}==>${C_RESET} %s\n" "$*"; }
warn() { printf "${C_YELLOW}!! ${C_RESET} %s\n" "$*"; }
die()  { printf "${C_RED}xx ${C_RESET} %s\n" "$*" >&2; exit 1; }

cd "$(dirname "$0")"
[ -f appinfo/info.xml ] || die "Run from the plugin root (expects appinfo/info.xml)."

VERSION=$(grep -o '<version>[^<]*</version>' appinfo/info.xml | sed 's/<[^>]*>//g')
APP_ID=fairregister
TARBALL="${APP_ID}-deploy-v${VERSION}.tar.gz"
STAGE_DIR=".deploy-stage"

if [ "$SKIP_BUILD" != "1" ]; then
  log "Building plugin for production..."
  rm -rf vendor/
  if [ -f composer.json ]; then
    if command -v composer >/dev/null 2>&1; then
      composer install --no-dev --optimize-autoloader --no-interaction
    else
      docker run --rm -v "$PWD":/app -w /app composer:2 install \
        --no-dev --optimize-autoloader --no-interaction
    fi
  fi
  npm install --no-progress --no-audit --no-fund
  npm run build
else
  warn "SKIP_BUILD=1 — skipping composer/npm step"
fi

log "Cleaning macOS metadata..."
find . -name '._*' -type f -delete 2>/dev/null || true
find . -name '.DS_Store' -type f -delete 2>/dev/null || true

# Stage with renamed directory so the tarball's top-level entry is "fairregister"
# instead of the parent folder name ("fairregister/").
log "Staging app contents..."
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR/$APP_ID"
# Copy everything except dev/build cruft into the stage
rsync -a \
  --exclude=node_modules \
  --exclude=vendor/bin \
  --exclude=.git \
  --exclude=.deploy-stage \
  --exclude=tests \
  --exclude=_reference \
  --exclude='.editorconfig' \
  --exclude='.gitignore' \
  --exclude='phpunit.xml*' \
  --exclude='psalm*' \
  --exclude='phpstan*' \
  --exclude='*.tar.gz' \
  --exclude='deploy-to-dev.sh' \
  --exclude='PLAN.md' \
  ./ "$STAGE_DIR/$APP_ID/"

log "Packing $TARBALL..."
COPYFILE_DISABLE=1 tar -czf "$TARBALL" -C "$STAGE_DIR" "$APP_ID"
rm -rf "$STAGE_DIR"

log "scp → $SSH_HOST:~/$TARBALL"
scp "$TARBALL" "${SSH_HOST}:~/${TARBALL}"

if [ -z "$REMOTE_CONTAINER" ]; then
  log "Auto-detecting remote container (filter: ${REMOTE_CONTAINER_FILTER})..."
  REMOTE_CONTAINER=$(ssh "$SSH_HOST" "docker ps --filter name=${REMOTE_CONTAINER_FILTER} --format '{{.Names}}' | head -1")
  [ -n "$REMOTE_CONTAINER" ] || die "No container matched. Set REMOTE_CONTAINER=<name|id>."
  log "Using container: $REMOTE_CONTAINER"
fi

log "Copying tarball into container..."
ssh "$SSH_HOST" "docker cp ${TARBALL} ${REMOTE_CONTAINER}:${REMOTE_APP_PATH}/${TARBALL}"

log "Unpacking + replacing existing install + setting ownership..."
ssh "$SSH_HOST" "docker exec ${REMOTE_CONTAINER} bash -c '
  set -e
  cd ${REMOTE_APP_PATH}
  rm -rf ${APP_ID}.bak
  if [ -d ${APP_ID} ]; then mv ${APP_ID} ${APP_ID}.bak; fi
  tar -xzf ${TARBALL}
  rm -f ${TARBALL}
  chown -R www-data:www-data ${APP_ID}
'"

log "Running occ upgrade + app:enable..."
ssh "$SSH_HOST" "docker exec -u www-data ${REMOTE_CONTAINER} php /var/www/html/occ upgrade || true"
ssh "$SSH_HOST" "docker exec -u www-data ${REMOTE_CONTAINER} php /var/www/html/occ app:enable ${APP_ID}"
# 1) upgrade can leave maintenance mode on if a migration ran — force off.
# 2) the .bak directory we left behind is picked up by NC's app autoloader
#    under a normalized id (e.g. "fairregisterbak"); leaving it there breaks
#    the next "occ upgrade". Clear it out now.
ssh "$SSH_HOST" "docker exec -u www-data ${REMOTE_CONTAINER} php /var/www/html/occ maintenance:mode --off || true"
ssh "$SSH_HOST" "docker exec ${REMOTE_CONTAINER} rm -rf ${REMOTE_APP_PATH}/${APP_ID}.bak"

log "Local cleanup..."
rm -f "$TARBALL"

log "Deployed v${VERSION} to ${SSH_HOST}:${REMOTE_CONTAINER}"
echo
echo "Next steps:"
echo "  1. Open the remote NC, log in as admin."
echo "  2. Admin Settings → fairregister → click 'Development' (or"
echo "     'Production') preset → Save. That's the only config the plugin"
echo "     needs — no OAuth/OIDC client setup required: the plugin issues"
echo "     one-time download tokens server-side and the fairregister"
echo "     frontend handles all user authentication on its own."
echo "  3. As any NC user, in Files → right-click a file → 'Register with"
echo "     fairregister'. A new tab opens at <frontend>/registerwork?…;"
echo "     log in there with your fairlogin account (id.fairkom.net) if"
echo "     not already authenticated."
echo "  4. Fill metadata, submit. The Work appears in /myworks on the"
echo "     frontend."
