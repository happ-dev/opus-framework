#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-30 10:00:00
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-30 10:00:00
#
# Usage: sudo OPUS_LANG=pl bash vendor/Opus/bin/reset.sh
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/reset.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/reset.sh

set -e

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# --- Absolute path ---
ABSOLUTE_PATH=$(dirname $(realpath "${BASH_SOURCE:-$0}"))
PROJECT_ROOT=$(realpath "$ABSOLUTE_PATH/../../..")

# --- Check dependencies ---
for cmd in psql jq; do
	if ! command -v "$cmd" > /dev/null 2>&1; then
		echo -e "${RED}[ERROR]${NC} Missing dependency: $cmd"
		exit 1
	fi
done

# --- Load language ---
OPUS_LANG="${OPUS_LANG:-en}"
LANG_FILE="$ABSOLUTE_PATH/lang/${OPUS_LANG}_install.sh"

if [ ! -f "$LANG_FILE" ]; then
	LANG_FILE="$ABSOLUTE_PATH/lang/en_install.sh"
fi

if [ ! -f "$LANG_FILE" ]; then
	echo -e "${RED}[ERROR]${NC} Language file not found: $LANG_FILE"
	exit 1
fi

sed -i -e 's/\r$//' "$LANG_FILE"
chmod 774 "$LANG_FILE"
source "$LANG_FILE"

# --- Read database name from global.json ---
GLOBAL_JSON="$PROJECT_ROOT/config/global.json"

if [ ! -f "$GLOBAL_JSON" ]; then
	echo -e "${RED}[ERROR]${NC} ${MSG_CFG_GLOBAL_MISSING}"
	exit 1
fi

DB_NAME=$(jq -r '.storage[0] | keys[0]' "$GLOBAL_JSON")

# --- Interactive confirmation ---
echo -e "\n${RED}${MSG_RESET_HEADER}${NC}\n"

read -rp "${MSG_RESET_DB_NAME} [$DB_NAME]: " INPUT_DB_NAME
DB_NAME="${INPUT_DB_NAME:-$DB_NAME}"

read -rp "${MSG_RESET_ROLE} [opus_admin]: " INPUT_ROLE_ADMIN
ROLE_ADMIN="${INPUT_ROLE_ADMIN:-opus_admin}"

read -rp "${MSG_RESET_ROLE} [opus_user]: " INPUT_ROLE_USER
ROLE_USER="${INPUT_ROLE_USER:-opus_user}"

echo -e "\n${MSG_RESET_WILL_DELETE}:"
echo -e "  - ${YELLOW}DATABASE${NC}: $DB_NAME"
echo -e "  - ${YELLOW}ROLE${NC}: $ROLE_ADMIN"
echo -e "  - ${YELLOW}ROLE${NC}: $ROLE_USER"
echo -e "  - config/local.json"
echo -e "  - config/secret.key"
echo -e "  - config/setup_report.json"
echo -e "  - config/install.log"
echo ""

# --- Confirmation ---
read -rp "${MSG_RESET_CONFIRM}: " CONFIRM

CONFIRM_UPPER=$(echo "$CONFIRM" | tr '[:lower:]' '[:upper:]')
if [ "$CONFIRM_UPPER" != "RESET" ]; then
	echo -e "${YELLOW}${MSG_RESET_ABORT}${NC}"
	exit 1
fi

# --- Connection parameters ---
echo ""
read -rp "${MSG_DB_HOST} [localhost]: " DB_HOST
DB_HOST="${DB_HOST:-localhost}"

read -rp "${MSG_DB_PORT} [5432]: " DB_PORT
DB_PORT="${DB_PORT:-5432}"

read -srp "${MSG_PG_PASS}: " PG_PASS
echo ""

if [ -z "$PG_PASS" ]; then
	echo -e "${RED}[ERROR]${NC} ${MSG_PG_PASS_EMPTY}"
	exit 1
fi

# --- Test connection ---
echo -e "\n${YELLOW}[...]${NC} ${MSG_CONN_TEST}"

if ! PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c "SELECT 1;" > /dev/null 2>&1; then
	echo -e "${RED}[ERROR]${NC} ${MSG_CONN_FAIL} ($DB_HOST:$DB_PORT)"
	exit 1
fi

echo -e "${GREEN}[OK]${NC} ${MSG_CONN_OK}"

# --- Terminate active connections ---
echo -e "\n${YELLOW}[...]${NC} ${MSG_RESET_DROP_DB} $DB_NAME"

PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c \
	"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" > /dev/null 2>&1 || true

# --- Drop database ---
PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c \
	"DROP DATABASE IF EXISTS $DB_NAME;" > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} DATABASE $DB_NAME"

# --- Drop roles ---
echo -e "\n${YELLOW}[...]${NC} ${MSG_RESET_DROP_ROLES}"

PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c \
	"DROP ROLE IF EXISTS $ROLE_ADMIN;" > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} ROLE $ROLE_ADMIN"

PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c \
	"DROP ROLE IF EXISTS $ROLE_USER;" > /dev/null 2>&1

echo -e "  ${GREEN}[OK]${NC} ROLE $ROLE_USER"

# --- Remove auth config ---
echo -e "\n${YELLOW}[...]${NC} ${MSG_RESET_AUTH}"

if jq -e '.auth' "$GLOBAL_JSON" > /dev/null 2>&1; then
	jq 'del(.auth)' "$GLOBAL_JSON" > "${GLOBAL_JSON}.tmp" && mv "${GLOBAL_JSON}.tmp" "$GLOBAL_JSON"
	echo -e "  ${GREEN}[OK]${NC} auth (global.json)"
else
	echo -e "  ${YELLOW}[--]${NC} auth (global.json)"
fi

# --- Remove files ---
echo -e "\n${YELLOW}[...]${NC} ${MSG_RESET_FILES}"

FILES_TO_REMOVE=(
	"config/local.json"
	"config/secret.key"
	"config/setup_report.json"
	"config/install.log"
)

for FILE in "${FILES_TO_REMOVE[@]}"; do
	FULL_PATH="$PROJECT_ROOT/$FILE"
	if [ -f "$FULL_PATH" ]; then
		rm -f "$FULL_PATH"
		echo -e "  ${GREEN}[OK]${NC} $FILE"
	else
		echo -e "  ${YELLOW}[--]${NC} $FILE"
	fi
done

# --- Done ---
echo -e "\n${GREEN}${MSG_RESET_DONE}${NC}"
echo -e "${MSG_RESET_REINSTALL}\n"
