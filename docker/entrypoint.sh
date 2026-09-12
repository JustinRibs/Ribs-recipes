#!/bin/sh
#
# Container start-up.
#
# Prepares the persistent volume, brings the database up to date and warms
# Laravel's caches. Everything here is safe to run on every boot.

set -e

APP_DIR=/app
STORAGE="${APP_DIR}/storage"
DATABASE="${DB_DATABASE:-${STORAGE}/database/database.sqlite}"

# --- Persistent directories --------------------------------------------------
# storage/ is a volume, so it starts empty on a brand-new install and these
# directories have to exist before Laravel writes anything.
mkdir -p \
    "${STORAGE}/app/public/media" \
    "${STORAGE}/app/private/imports" \
    "${STORAGE}/framework/cache/data" \
    "${STORAGE}/framework/sessions" \
    "${STORAGE}/framework/testing" \
    "${STORAGE}/framework/views" \
    "${STORAGE}/logs" \
    "$(dirname "${DATABASE}")" \
    "${APP_DIR}/bootstrap/cache"

# --- Database ----------------------------------------------------------------
if [ ! -f "${DATABASE}" ]; then
    echo "Creating a new SQLite database at ${DATABASE}"
    touch "${DATABASE}"
fi

chown -R www-data:www-data "${STORAGE}" "${APP_DIR}/bootstrap/cache"

# --- Public link to uploaded media -------------------------------------------
# public/ lives in the image while storage/ is a volume, so the symlink has to
# be recreated on every boot.
rm -rf "${APP_DIR}/public/storage"
php artisan storage:link --force --quiet

# --- Schema ------------------------------------------------------------------
php artisan migrate --force --no-interaction

# Seed the starting taxonomy and the owner account, but only once — the
# seeders are idempotent, and on an established install this is a no-op.
if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan db:seed --force --no-interaction --class=Database\\Seeders\\OwnerSeeder
    php artisan db:seed --force --no-interaction --class=Database\\Seeders\\CategorySeeder
    php artisan db:seed --force --no-interaction --class=Database\\Seeders\\TagSeeder
fi

# --- Caches ------------------------------------------------------------------
# Cleared first so a deploy never runs against a previous build's cached paths.
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan event:cache --quiet

echo "Ribs Recipes is ready."

exec "$@"
