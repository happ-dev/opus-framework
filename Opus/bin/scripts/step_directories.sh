#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:07
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 16:22:46
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_directories.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_directories.sh

# Step: Directories and permissions

step_directories() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_DIR_CHECK}"

	# dir:permissions:group
	DIRS=(
		"apps:0755:"
		"config:0775:www-data"
		"public:0775:www-data"
		"public/vendor:0775:"
		"public/vendor/opus:0775:www-data"
		"public/img:0755:"
		"public/var:0775:www-data"
		"sessions:0775:www-data"
	)

	for ENTRY in "${DIRS[@]}"; do
		IFS=':' read -r DIR PERMS GROUP <<< "$ENTRY"
		FULL_PATH="$PROJECT_ROOT/$DIR"

		if [ ! -d "$FULL_PATH" ]; then
			mkdir -p "$FULL_PATH"
			chmod "$PERMS" "$FULL_PATH"
			[ -n "$GROUP" ] && chgrp "$GROUP" "$FULL_PATH"
			[ -n "$SUDO_USER" ] && chown "$SUDO_USER" "$FULL_PATH"
			echo -e "  ${GREEN}[${MSG_DIR_CREATED}]${NC} $DIR"
		else
			CURRENT_PERMS=$(stat -c "%a" "$FULL_PATH")
			CURRENT_GROUP=$(stat -c "%G" "$FULL_PATH")
			NEEDS_FIX=false

			if [ "$CURRENT_PERMS" != "${PERMS#0}" ]; then
				chmod "$PERMS" "$FULL_PATH"
				NEEDS_FIX=true
			fi

			if [ -n "$GROUP" ] && [ "$CURRENT_GROUP" != "$GROUP" ]; then
				chgrp "$GROUP" "$FULL_PATH"
				NEEDS_FIX=true
			fi

			if [ "$NEEDS_FIX" = true ]; then
				echo -e "  ${YELLOW}[${MSG_DIR_FIXED}]${NC} $DIR"
			else
				echo -e "  ${GREEN}[${MSG_DIR_OK}]${NC} $DIR"
			fi
		fi
	done
}
