#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:08
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 15:21:44
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_sql.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_sql.sh

# Step: Execute SQL files

INSTALL_LOG="$PROJECT_ROOT/config/install.log"

exec_sql() {
	local FILE="$1"
	local LABEL="$2"
	local DB="$3"

	# Skip if already executed
	if [ -f "$INSTALL_LOG" ] && grep -qx "$LABEL" "$INSTALL_LOG"; then
		echo -e "  ${YELLOW}[${MSG_SQL_SKIP}]${NC} $LABEL"
		return
	fi

	if [ ! -f "$FILE" ]; then
		echo -e "  ${YELLOW}[${MSG_SQL_SKIP}]${NC} $LABEL"
		return
	fi

	if sed \
		-e "s/\\\$opus_admin_password\\\$/$OPUS_ADMIN_PASS/g" \
		-e "s/\\\$opus_user_password\\\$/$OPUS_USER_PASS/g" \
		-e "s/\\\$root_password\\\$/$ROOT_HASH/g" \
		-e "s/\\\$api_password\\\$/$API_HASH/g" \
		"$FILE" | PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -d "$DB" -q 2>/dev/null; then
		echo "$LABEL" >> "$INSTALL_LOG"
		[ -n "$SUDO_USER" ] && chown "$SUDO_USER" "$INSTALL_LOG"
		echo -e "  ${GREEN}[${MSG_SQL_OK}]${NC} $LABEL"
	else
		echo -e "  ${RED}[${MSG_SQL_FAIL}]${NC} $LABEL"
		exit 1
	fi
}

step_sql() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_SQL_EXEC}"

	local SQL_DIR="$ABSOLUTE_PATH/../sql"

	exec_sql "$SQL_DIR/role.sql" "role.sql" "postgres"
	exec_sql "$SQL_DIR/create.sql" "create.sql" "postgres"
	exec_sql "$SQL_DIR/settings.sql" "settings.sql" "opus"
	exec_sql "$SQL_DIR/scheme/groups.sql" "groups.sql" "opus"
	exec_sql "$SQL_DIR/scheme/logs.sql" "logs.sql" "opus"
	exec_sql "$SQL_DIR/scheme/users.sql" "users.sql" "opus"
	exec_sql "$SQL_DIR/scheme/demo.sql" "demo.sql" "opus"
}
