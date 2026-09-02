#!/usr/bin/env bash
#
# Push local CONTENT to the LIVE database (and media files).
#
#   scripts/db-push.sh            # asks for confirmation
#   scripts/db-push.sh --yes      # non-interactive
#   scripts/db-push.sh --no-media # DB only
#
# Only the content tables in PUSH_TABLES (scripts/db-common.sh) are replaced on
# live. Live-generated data (form submissions, SEO results), auth/session state
# and the migrations table are left untouched. A full backup of the live DB is
# taken on the server first (~/db-backups) so the push can be rolled back.
#
# Anything edited on live (admin or the MCP connector) since the last
# scripts/db-pull.sh in those tables WILL be overwritten. Pull first if unsure.
set -euo pipefail
cd "$(dirname "$0")/.."
source scripts/db-common.sh
load_local_env

ASSUME_YES=0; SYNC_MEDIA=1
for arg in "$@"; do
    case "$arg" in
        --yes) ASSUME_YES=1 ;;
        --no-media) SYNC_MEDIA=0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

echo "This replaces these tables on LIVE with your local copy:"
printf '    %s\n' "${PUSH_TABLES[@]}"
if [ "$ASSUME_YES" != 1 ]; then
    read -r -p "Type LIVE to continue: " answer
    [ "$answer" = "LIVE" ] || { echo "Aborted."; exit 1; }
fi

TS=$(date +%Y%m%d-%H%M%S)
mkdir -p "$LOCAL_BACKUPS"

say "1/5  Full backup of the live database on the server"
REMOTE_BACKUP=$(ssh -o BatchMode=yes "$SSH_TARGET" "$(remote_dump_cmd live-before-push)" 2>/dev/null | tail -1)
[ -n "$REMOTE_BACKUP" ] || { echo "Remote backup failed; not pushing." >&2; exit 1; }
echo "    $REMOTE_BACKUP"

say "2/5  Dumping local content tables"
LOCAL_FILE="$LOCAL_BACKUPS/push-$TS.sql.gz"
# shellcheck disable=SC2046
"$MYSQLDUMP" $(local_mysql_args) --single-transaction --no-tablespaces --set-gtid-purged=OFF \
    "$LOCAL_DB_NAME" "${PUSH_TABLES[@]}" 2>/dev/null | gzip > "$LOCAL_FILE"
echo "    $LOCAL_FILE ($(du -h "$LOCAL_FILE" | cut -f1))"

say "3/5  Importing on the server"
scp -q -o BatchMode=yes "$LOCAL_FILE" "$SSH_TARGET:$REMOTE_BACKUPS/" 2>/dev/null
ssh -o BatchMode=yes "$SSH_TARGET" bash <<REMOTE 2>/dev/null
set -euo pipefail
cd ~/$REMOTE_APP
eval "\$(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env | sed 's/^/export /')"
gunzip -c ~/$REMOTE_BACKUPS/$(basename "$LOCAL_FILE") \
  | mysql -h"\$DB_HOST" -P"\$DB_PORT" -u"\$DB_USERNAME" -p"\$DB_PASSWORD" "\$DB_DATABASE" 2>/dev/null
php artisan optimize:clear -q
php artisan optimize -q
php artisan filament:optimize -q 2>/dev/null || true
REMOTE

if [ "$SYNC_MEDIA" = 1 ]; then
    say "4/5  Media local -> live (adds/updates only, never deletes)"
    rsync -az --no-perms --no-owner --no-group -e "ssh -o BatchMode=yes" \
        "$MEDIA_DIR" "$SSH_TARGET:$REMOTE_APP/$MEDIA_DIR" 2>/dev/null
else
    say "4/5  Media skipped (--no-media)"
fi

say "5/5  Done"
printf '\n✅ Live content replaced from local. Rollback: import %s on the server.\n' "$REMOTE_BACKUP"
