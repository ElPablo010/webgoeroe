#!/usr/bin/env bash
#
# Pull the LIVE database (and media files) into the local dev environment.
#
#   scripts/db-pull.sh            # full DB + media
#   scripts/db-pull.sh --no-media # DB only
#
# Local data is disposable: the local DB is dropped and recreated from the live
# dump. A gzipped backup of the local DB is written to storage/db-backups first.
set -euo pipefail
cd "$(dirname "$0")/.."
source scripts/db-common.sh
load_local_env

SYNC_MEDIA=1
[ "${1:-}" = "--no-media" ] && SYNC_MEDIA=0

TS=$(date +%Y%m%d-%H%M%S)
mkdir -p "$LOCAL_BACKUPS"

say "1/5  Local backup -> $LOCAL_BACKUPS/local-before-pull-$TS.sql.gz"
# shellcheck disable=SC2046
"$MYSQLDUMP" $(local_mysql_args) --single-transaction --no-tablespaces --set-gtid-purged=OFF \
    "$LOCAL_DB_NAME" 2>/dev/null | gzip > "$LOCAL_BACKUPS/local-before-pull-$TS.sql.gz"

say "2/5  Dumping live database on the server"
REMOTE_FILE=$(ssh -o BatchMode=yes "$SSH_TARGET" "$(remote_dump_cmd live)" 2>/dev/null | tail -1)
[ -n "$REMOTE_FILE" ] || { echo "Remote dump failed." >&2; exit 1; }
LOCAL_FILE="$LOCAL_BACKUPS/$(basename "$REMOTE_FILE")"
scp -q -o BatchMode=yes "$SSH_TARGET:$REMOTE_FILE" "$LOCAL_FILE" 2>/dev/null
gunzip -t "$LOCAL_FILE"
echo "    $LOCAL_FILE ($(du -h "$LOCAL_FILE" | cut -f1))"

say "3/5  Recreating local database '$LOCAL_DB_NAME' and importing"
# shellcheck disable=SC2046
"$MYSQL" $(local_mysql_args) -e "DROP DATABASE IF EXISTS \`$LOCAL_DB_NAME\`; CREATE DATABASE \`$LOCAL_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# shellcheck disable=SC2046
gunzip -c "$LOCAL_FILE" | "$MYSQL" $(local_mysql_args) "$LOCAL_DB_NAME"

say "4/5  Clearing local caches"
php artisan optimize:clear -q

if [ "$SYNC_MEDIA" = 1 ]; then
    say "5/5  Media live -> local (adds/updates only, never deletes)"
    rsync -az --no-perms --no-owner --no-group -e "ssh -o BatchMode=yes" \
        "$SSH_TARGET:$REMOTE_APP/$MEDIA_DIR" "$MEDIA_DIR" 2>/dev/null
else
    say "5/5  Media skipped (--no-media)"
fi

printf '\n✅ Local environment now mirrors live (as of %s).\n' "$(date '+%d/%m/%Y %H:%M')"
