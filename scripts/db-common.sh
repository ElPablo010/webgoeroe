#!/usr/bin/env bash
# Shared config for scripts/db-pull.sh and scripts/db-push.sh.
# Sourced, not executed.

SSH_TARGET="dewebgoeroebe@ssh.dewebgoeroe.be"
REMOTE_APP="webgoeroe"                     # project dir in the server home
REMOTE_BACKUPS="db-backups"                # relative to the server home
LOCAL_BACKUPS="storage/db-backups"         # gitignored

# Content tables that travel local -> live. Everything else on live is either
# generated there (form_submissions, seo_*), auth/session state (users, oauth_*,
# personal_access_tokens, sessions) or schema bookkeeping (migrations) and must
# never be overwritten by a local copy.
PUSH_TABLES=(pages page_sections posts case_studies menus menu_items settings website_media redirects)

MEDIA_DIR="storage/app/public/"

# Herd ships its own MySQL client; fall back to whatever is on PATH.
HERD_BIN="$HOME/Library/Application Support/Herd/bin"
if [ -x "$HERD_BIN/mysql" ]; then
    MYSQL="$HERD_BIN/mysql"; MYSQLDUMP="$HERD_BIN/mysqldump"
else
    MYSQL="$(command -v mysql)"; MYSQLDUMP="$(command -v mysqldump)"
fi

# Read DB_* from a .env file into the current shell (LOCAL_DB_* prefix).
load_local_env() {
    local key val
    while IFS='=' read -r key val; do
        case "$key" in
            DB_HOST) LOCAL_DB_HOST="$val" ;;
            DB_PORT) LOCAL_DB_PORT="$val" ;;
            DB_DATABASE) LOCAL_DB_NAME="$val" ;;
            DB_USERNAME) LOCAL_DB_USER="$val" ;;
            DB_PASSWORD) LOCAL_DB_PASS="$val" ;;
        esac
    done < <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env | sed 's/"//g')
    : "${LOCAL_DB_HOST:=127.0.0.1}" "${LOCAL_DB_PORT:=3306}"
}

local_mysql_args() {
    printf -- '-h%s -P%s -u%s' "$LOCAL_DB_HOST" "$LOCAL_DB_PORT" "$LOCAL_DB_USER"
    [ -n "${LOCAL_DB_PASS:-}" ] && printf -- ' -p%s' "$LOCAL_DB_PASS"
}

# Runs on the server: dump the live DB (optionally only given tables) to a
# gzipped file under ~/db-backups and print its path.
remote_dump_cmd() {
    local label="$1"; shift
    local tables="$*"
    cat <<REMOTE
set -euo pipefail
cd ~/$REMOTE_APP
eval "\$(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env | sed 's/^/export /')"
mkdir -p ~/$REMOTE_BACKUPS
F=~/$REMOTE_BACKUPS/$label-\$(date +%Y%m%d-%H%M%S).sql.gz
( mysqldump -h"\$DB_HOST" -P"\$DB_PORT" -u"\$DB_USERNAME" -p"\$DB_PASSWORD" \
    --single-transaction --no-tablespaces --set-gtid-purged=OFF "\$DB_DATABASE" $tables 2>/dev/null \
  || mysqldump -h"\$DB_HOST" -P"\$DB_PORT" -u"\$DB_USERNAME" -p"\$DB_PASSWORD" \
    --single-transaction --no-tablespaces "\$DB_DATABASE" $tables 2>/dev/null ) | gzip > "\$F"
echo "\$F"
REMOTE
}

say() { printf '\n==> %s\n' "$*"; }
