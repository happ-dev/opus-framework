#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:08
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 13:57:35
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_passwords.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_passwords.sh

# Step: Password generation

gen_pass() {
	tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 16
}

# Ask for password: hidden input, confirm if custom
# Usage: ask_pass "label" VARIABLE_NAME
ask_pass() {
	local LABEL="$1"
	local PROPOSED
	PROPOSED=$(gen_pass)

	while true; do
		read -srp "  ${LABEL} [${PROPOSED}]: " INPUT
		echo ""

		if [ -z "$INPUT" ]; then
			eval "$2='$PROPOSED'"
			return
		fi

		read -srp "  ${MSG_PASS_CONFIRM}: " CONFIRM
		echo ""

		if [ "$INPUT" = "$CONFIRM" ]; then
			eval "$2='$INPUT'"
			return
		fi

		echo -e "  ${RED}[ERROR]${NC} ${MSG_PASS_MISMATCH}"
	done
}

step_passwords() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_PASS_GEN}"

	ask_pass "$MSG_PASS_OPUS_ADMIN" OPUS_ADMIN_PASS
	ask_pass "$MSG_PASS_OPUS_USER" OPUS_USER_PASS
	ask_pass "$MSG_PASS_ROOT" ROOT_PASS
	ask_pass "$MSG_PASS_API" API_PASS

	# SHA-256 hashes for application users (SQL)
	ROOT_HASH=$(echo -n "$ROOT_PASS" | sha256sum | awk '{print $1}')
	API_HASH=$(echo -n "$API_PASS" | sha256sum | awk '{print $1}')

	echo -e "  ${GREEN}[OK]${NC}"
}
