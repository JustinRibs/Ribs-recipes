#!/usr/bin/env bash
#
# Ribs Recipes — backup
#
# Takes a consistent snapshot of everything that cannot be rebuilt: the SQLite
# database and the uploaded photos. Timestamped, verified, and pruned to a
# retention window.
#
#   ./scripts/backup.sh                       # back up the running container
#   ./scripts/backup.sh --dir /mnt/nas/ribs   # somewhere else
#   ./scripts/backup.sh --keep 30             # keep a month
#   ./scripts/backup.sh --local               # no Docker; use local paths
#
# Everything can also come from the environment, which is what a cron entry
# usually does:
#
#   BACKUP_DIR=/mnt/nas/ribs BACKUP_KEEP=30 /srv/ribs-recipes/scripts/backup.sh
#
# Suggested crontab entry (03:00 daily):
#   0 3 * * * cd /srv/ribs-recipes && ./scripts/backup.sh >> /var/log/ribs-backup.log 2>&1
#
# Restoring is documented in README.md under "Backups".

set -euo pipefail

cd "$(dirname "$0")/.."

# --- Configuration -----------------------------------------------------------

BACKUP_DIR="${BACKUP_DIR:-./backups}"
BACKUP_KEEP="${BACKUP_KEEP:-14}"          # how many dated snapshots to keep
CONTAINER="${RIBS_CONTAINER:-ribs-recipes}"
MODE="docker"

# Paths inside the container (and on the host in --local mode).
DB_PATH="${RIBS_DB_PATH:-/app/storage/database/database.sqlite}"
MEDIA_PATH="${RIBS_MEDIA_PATH:-/app/storage/app/public/media}"

while [ $# -gt 0 ]; do
    case "$1" in
        --dir)    BACKUP_DIR="$2"; shift 2 ;;
        --keep)   BACKUP_KEEP="$2"; shift 2 ;;
        --local)  MODE="local"; shift ;;
        --container) CONTAINER="$2"; shift 2 ;;
        -h|--help)
            sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
            exit 0 ;;
        *)
            echo "Unknown option: $1" >&2
            exit 2 ;;
    esac
done

if [ "$MODE" = "local" ]; then
    DB_PATH="${RIBS_DB_PATH:-./storage/database/database.sqlite}"
    MEDIA_PATH="${RIBS_MEDIA_PATH:-./storage/app/public/media}"
fi

STAMP="$(date +%Y-%m-%d-%H%M%S)"
DEST="${BACKUP_DIR}/${STAMP}"

log() { printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
fail() { printf '%s  ERROR: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2; exit 1; }

# --- Preflight ---------------------------------------------------------------

if [ "$MODE" = "docker" ]; then
    command -v docker >/dev/null 2>&1 || fail "docker is not installed. Use --local instead."

    docker inspect --format '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -q true \
        || fail "Container '${CONTAINER}' is not running. Start the stack, or use --local."
else
    command -v sqlite3 >/dev/null 2>&1 || fail "sqlite3 is not installed."
    [ -f "$DB_PATH" ] || fail "No database at ${DB_PATH}"
fi

mkdir -p "$DEST" || fail "Cannot write to ${BACKUP_DIR}"

# Clean up a half-written snapshot if anything below fails.
trap 'rm -rf "$DEST"' ERR

log "Backing up to ${DEST}"

# --- Database ----------------------------------------------------------------
#
# sqlite3's .backup uses the online backup API. It is the only correct way to
# copy a live SQLite database: plain cp can capture a torn page, and in WAL
# mode it would silently miss every committed change still sitting in the -wal
# file. .backup takes a consistent snapshot while the site keeps serving.

log "Snapshotting the database…"

if [ "$MODE" = "docker" ]; then
    docker exec "$CONTAINER" sh -c \
        "sqlite3 '${DB_PATH}' \".backup '/tmp/ribs-backup.sqlite'\"" \
        || fail "sqlite3 .backup failed inside the container"

    docker cp "${CONTAINER}:/tmp/ribs-backup.sqlite" "${DEST}/database.sqlite" >/dev/null \
        || fail "Could not copy the snapshot out of the container"

    docker exec "$CONTAINER" rm -f /tmp/ribs-backup.sqlite || true
else
    sqlite3 "$DB_PATH" ".backup '${DEST}/database.sqlite'" \
        || fail "sqlite3 .backup failed"
fi

# --- Verify ------------------------------------------------------------------
#
# A backup nobody has checked is a rumour. This is cheap and catches the two
# failure modes that matter: a truncated copy, and a corrupt source.

log "Verifying the snapshot…"

if command -v sqlite3 >/dev/null 2>&1; then
    INTEGRITY="$(sqlite3 "${DEST}/database.sqlite" 'pragma integrity_check;' 2>&1 || true)"
elif [ "$MODE" = "docker" ]; then
    docker cp "${DEST}/database.sqlite" "${CONTAINER}:/tmp/ribs-verify.sqlite" >/dev/null
    INTEGRITY="$(docker exec "$CONTAINER" sqlite3 /tmp/ribs-verify.sqlite 'pragma integrity_check;' 2>&1 || true)"
    docker exec "$CONTAINER" rm -f /tmp/ribs-verify.sqlite || true
else
    INTEGRITY="ok"
fi

[ "$INTEGRITY" = "ok" ] || fail "Integrity check failed: ${INTEGRITY}"

RECIPES="unknown"

if command -v sqlite3 >/dev/null 2>&1; then
    RECIPES="$(sqlite3 "${DEST}/database.sqlite" 'select count(*) from recipes where deleted_at is null;' 2>/dev/null || echo unknown)"
fi

# --- Photos ------------------------------------------------------------------

log "Copying photos…"

mkdir -p "${DEST}/images"

if [ "$MODE" = "docker" ]; then
    # A tar stream keeps permissions and empty directories intact, and avoids
    # `docker cp` quirks when the source directory does not exist yet.
    docker exec "$CONTAINER" sh -c \
        "[ -d '${MEDIA_PATH}' ] && tar -cf - -C '${MEDIA_PATH}' . || tar -cf - -T /dev/null" \
        | tar -xf - -C "${DEST}/images" \
        || fail "Could not copy the photos out of the container"
else
    if [ -d "$MEDIA_PATH" ]; then
        cp -R "${MEDIA_PATH}/." "${DEST}/images/"
    fi
fi

IMAGE_COUNT="$(find "${DEST}/images" -type f | wc -l | tr -d ' ')"
SIZE="$(du -sh "$DEST" | cut -f1)"

# --- Manifest ----------------------------------------------------------------

cat > "${DEST}/manifest.txt" <<MANIFEST
Ribs Recipes backup
===================

Taken           : $(date '+%Y-%m-%d %H:%M:%S %Z')
Host            : $(hostname)
Mode            : ${MODE}
Source database : ${DB_PATH}
Source photos   : ${MEDIA_PATH}

Recipes         : ${RECIPES}
Photo files     : ${IMAGE_COUNT}
Total size      : ${SIZE}
Integrity check : ${INTEGRITY}

Restore
-------

  1. Stop the application:
         docker compose stop app scheduler

  2. Put the database back:
         docker cp ${DEST}/database.sqlite \\
             ${CONTAINER}:/app/storage/database/database.sqlite

     WAL sidecar files from the old database must not survive the swap:
         docker compose run --rm --entrypoint sh app -c \\
             'rm -f /app/storage/database/database.sqlite-wal /app/storage/database/database.sqlite-shm'

  3. Put the photos back:
         tar -cf - -C ${DEST}/images . | \\
             docker exec -i ${CONTAINER} tar -xf - -C /app/storage/app/public/media

  4. Fix ownership and start again:
         docker compose run --rm --entrypoint sh app -c 'chown -R www-data:www-data /app/storage'
         docker compose up -d

  5. Confirm, then rebuild the search index if anything looks stale:
         docker compose exec app php artisan recipes:reindex

Full instructions are in README.md under "Backups".
MANIFEST

trap - ERR

# --- Retention ---------------------------------------------------------------

if [ "$BACKUP_KEEP" -gt 0 ]; then
    # Snapshot directories sort chronologically because of the name format.
    EXISTING="$(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20*' | sort)"
    TOTAL="$(printf '%s\n' "$EXISTING" | grep -c . || true)"

    if [ "$TOTAL" -gt "$BACKUP_KEEP" ]; then
        REMOVE=$((TOTAL - BACKUP_KEEP))
        log "Pruning ${REMOVE} snapshot(s), keeping the newest ${BACKUP_KEEP}"

        printf '%s\n' "$EXISTING" | head -n "$REMOVE" | while read -r old; do
            [ -n "$old" ] && rm -rf "$old" && log "  removed $(basename "$old")"
        done
    fi
fi

log "Done — ${RECIPES} recipes, ${IMAGE_COUNT} photo files, ${SIZE}"
log "Snapshot: ${DEST}"
