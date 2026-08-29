#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-28 21:04:13
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 21:28:20
#
# Usage: sudo OPUS_LANG=pl bash vendor/Opus/bin/install.sh
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/install.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/install.sh

set -e

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# --- Absolute path ---
ABSOLUTE_PATH=$(dirname $(realpath "${BASH_SOURCE:-$0}"))
PROJECT_ROOT=$(realpath "$ABSOLUTE_PATH/../../..")

echo $ABSOLUTE_PATH
echo $PROJECT_ROOT

# --- Check dependencies ---
for cmd in psql jq sha256sum; do
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

# --- Load steps ---
STEP_FILES=(
	"step_connection.sh"
	"step_directories.sh"
	"step_passwords.sh"
	"step_config.sh"
	"step_sql.sh"
	"step_secret.sh"
)

for STEP_FILE in "${STEP_FILES[@]}"; do
	STEP_PATH="$ABSOLUTE_PATH/scripts/$STEP_FILE"

	if [ ! -f "$STEP_PATH" ]; then
		echo -e "${RED}[ERROR]${NC} Step file not found: $STEP_PATH"
		exit 1
	fi

	sed -i -e 's/\r$//' "$STEP_PATH"
	chmod 774 "$STEP_PATH"
	source "$STEP_PATH"
done

# --- Execute ---
step_connection
step_directories
step_passwords
step_config
step_sql
step_secret

# --- Encrypt config + fix secret.key owner ---
php "$PROJECT_ROOT/public/index.php" setup welcome
chown "$SUDO_USER:www-data" "$PROJECT_ROOT/config/secret.key"
chmod 0660 "$PROJECT_ROOT/config/secret.key"
