#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:09
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 15:22:04
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_secret.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_secret.sh

# Step: Save passwords to ~/opus_secret.txt

step_secret() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_SECRET_SAVE}"

	local SECRET_FILE

	if [ -n "$SUDO_USER" ]; then
		SECRET_FILE="/home/${SUDO_USER}/opus_secret.txt"
	else
		SECRET_FILE="${HOME}/opus_secret.txt"
	fi

	cat > "$SECRET_FILE" <<-EOF
		# Opus Setup - generated passwords
		# Date: $(date '+%Y-%m-%d %H:%M:%S')
		# ${MSG_SECRET_WARN}

		opus_admin: ${OPUS_ADMIN_PASS}
		opus_user:  ${OPUS_USER_PASS}
		root:       ${ROOT_PASS}
		api:        ${API_PASS}
	EOF

	chmod 0400 "$SECRET_FILE"
	[ -n "$SUDO_USER" ] && chown "${SUDO_USER}:${SUDO_USER}" "$SECRET_FILE"

	echo -e "  ${GREEN}[OK]${NC} ${MSG_SECRET_OK} ${SECRET_FILE}"
	echo -e "  ${YELLOW}${MSG_SECRET_WARN}${NC}"
}
