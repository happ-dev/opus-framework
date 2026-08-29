#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:07
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-08-29 15:22:40
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_connection.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_connection.sh

# Step: Connection parameters + test

step_connection() {
	echo -e "\n${GREEN}${MSG_HEADER}${NC}\n"

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

	echo -e "\n${YELLOW}[...]${NC} ${MSG_CONN_TEST}"

	if ! PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -c "SELECT 1;" > /dev/null 2>&1; then
		echo -e "${RED}[ERROR]${NC} ${MSG_CONN_FAIL} ($DB_HOST:$DB_PORT)"
		exit 1
	fi

	echo -e "${GREEN}[OK]${NC} ${MSG_CONN_OK}"
}
