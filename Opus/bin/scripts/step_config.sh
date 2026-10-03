#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:08
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 13:48:52
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_config.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_config.sh

# Step: Create local.json

step_config() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_CFG_CREATE}"

	local GLOBAL_JSON="$PROJECT_ROOT/config/global.json"
	local LOCAL_JSON="$PROJECT_ROOT/config/local.json"

	if [ ! -f "$GLOBAL_JSON" ]; then
		echo -e "  ${RED}[ERROR]${NC} ${MSG_CFG_GLOBAL_MISSING}"
		exit 1
	fi

	# Read storage key name from global.json (e.g. "happ")
	local STORAGE_KEY
	STORAGE_KEY=$(jq -r '.storage[0] | keys[0]' "$GLOBAL_JSON")

	# Remove old files
	rm -f "$LOCAL_JSON"
	rm -f "$PROJECT_ROOT/config/secret.key"

	# Create local.json
	jq -n \
		--arg key "$STORAGE_KEY" \
		--arg user "opus_user" \
		--arg pass "$OPUS_USER_PASS" \
		--arg host "$DB_HOST" \
		--arg port "$DB_PORT" \
		'{storage: [{($key): {user: $user, pass: $pass, host: $host, port: $port}}]}' \
		> "$LOCAL_JSON"

	chmod 0664 "$LOCAL_JSON"
	chgrp www-data "$LOCAL_JSON"
	[ -n "$SUDO_USER" ] && chown "${SUDO_USER}" "$LOCAL_JSON"

	echo -e "  ${GREEN}[OK]${NC} ${MSG_CFG_OK}"
}
