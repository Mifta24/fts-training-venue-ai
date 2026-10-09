#!/usr/bin/env bash
#
# Deploy FTS Training Venue AI to the Plesk server.
#
#   ./deploy.sh              build assets, pull the pushed code, migrate, refresh caches
#   ./deploy.sh --no-build   skip the local `npm run build` and the asset upload
#   ./deploy.sh --seed-demo  also re-run DemoVenueSeeder (resets demo rooms, inventory and knowledge base)
#
# The server pulls from GitHub, so push your commits first. It has no Node,
# so the Vite build is made here and uploaded with rsync.
# Needs the `plesk` SSH alias (see ~/.ssh/config).

set -euo pipefail

SSH_HOST="${DEPLOY_SSH_HOST:-plesk}"
SITE_DIR="${DEPLOY_SITE_DIR:-/var/www/vhosts/fts-tech.co.id/training-venue-ai.fts-tech.co.id}"
SITE_USER="${DEPLOY_SITE_USER:-fts-tech.co.id_md8klw10xxd}"
SITE_URL="${DEPLOY_SITE_URL:-https://training-venue-ai.fts-tech.co.id}"
BRANCH="${DEPLOY_BRANCH:-master}"
PHP_BIN="/opt/plesk/php/8.4/bin/php"

BUILD=1
SEED_DEMO=0
for arg in "$@"; do
    case "$arg" in
        --no-build) BUILD=0 ;;
        --seed-demo) SEED_DEMO=1 ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")"

step() { printf '\n\033[1;33m==> %s\033[0m\n' "$1"; }
fail() { printf '\033[1;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

step "Checking the local repository"
[ "$(git rev-parse --abbrev-ref HEAD)" = "$BRANCH" ] || fail "You are not on '$BRANCH'."
git fetch -q origin "$BRANCH"
[ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ] \
    || fail "Local '$BRANCH' differs from origin. Push (or pull) first: the server deploys what is on GitHub."
if [ -n "$(git status --porcelain)" ]; then
    printf '\033[1;33mWarning:\033[0m uncommitted changes are NOT deployed (the server pulls from GitHub).\n'
fi

if [ "$BUILD" -eq 1 ]; then
    step "Building assets"
    npm run build
fi

step "Pulling the code on the server"
ssh "$SSH_HOST" "set -e
cd '$SITE_DIR'
sudo -u '$SITE_USER' git -c safe.directory='$SITE_DIR' fetch -q origin '$BRANCH'
sudo -u '$SITE_USER' git -c safe.directory='$SITE_DIR' reset -q --hard 'origin/$BRANCH'
sudo -u '$SITE_USER' sh -c 'PATH=/opt/plesk/php/8.4/bin:\$PATH composer install --no-dev --optimize-autoloader --no-interaction --quiet'
"

if [ "$BUILD" -eq 1 ]; then
    step "Uploading assets"
    rsync -az --delete public/build/ "$SSH_HOST:$SITE_DIR/public/build/"
fi

step "Migrating and refreshing caches"
ssh "$SSH_HOST" "set -e
cd '$SITE_DIR'
chown -R '$SITE_USER':psacln public/build 2>/dev/null || true
art() { sudo -u '$SITE_USER' '$PHP_BIN' artisan \"\$@\"; }
art migrate --force
if [ '$SEED_DEMO' = '1' ]; then art db:seed --class=DemoVenueSeeder --force; fi
art config:cache
art route:cache
art view:cache
art queue:restart
chmod -R ug+rwX storage bootstrap/cache
"

step "Checking the live site"
code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/admin/login")
[ "$code" = "200" ] || fail "$SITE_URL/admin/login answered HTTP $code."
printf 'Deployed %s (%s) -> %s\n' "$(git rev-parse --short HEAD)" "$BRANCH" "$SITE_URL"
