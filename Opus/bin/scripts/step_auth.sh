#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-10-05 12:00:00
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-10-03 12:05:22
#
# sed -i -e 's/\r$//' /opt/happ.local/vendor/Opus/bin/scripts/step_auth.sh
# chmod -R 774 /opt/happ.local/vendor/Opus/bin/scripts/step_auth.sh

# Step: Auth configuration

step_auth() {
	echo -e "\n${YELLOW}[...]${NC} ${MSG_AUTH_CONFIG}"

	local GLOBAL_JSON="$PROJECT_ROOT/config/global.json"
	local LOCAL_JSON="$PROJECT_ROOT/config/local.json"

	read -rp "  ${MSG_AUTH_DRIVER} [local]: " AUTH_DRIVER
	AUTH_DRIVER="${AUTH_DRIVER:-local}"

	if [ "$AUTH_DRIVER" = "local" ]; then
		jq '.auth = {driver: "local"}' "$GLOBAL_JSON" > "${GLOBAL_JSON}.tmp" && mv "${GLOBAL_JSON}.tmp" "$GLOBAL_JSON"
		fix_global_perms "$GLOBAL_JSON"
		echo -e "  ${GREEN}[OK]${NC} ${MSG_AUTH_OK}"
		return
	fi

	if [ "$AUTH_DRIVER" != "ldap" ]; then
		echo -e "  ${RED}[ERROR]${NC} ${MSG_AUTH_INVALID_DRIVER}"
		exit 1
	fi

	# --- LDAP connection ---
	read -rp "  ${MSG_AUTH_HOST} [ldaps://dc.opus.local]: " LDAP_HOST
	LDAP_HOST="${LDAP_HOST:-ldaps://dc.opus.local}"

	read -rp "  ${MSG_AUTH_PORT} [636]: " LDAP_PORT
	LDAP_PORT="${LDAP_PORT:-636}"

	# --- Domain → base_dn, user_dn ---
	read -rp "  ${MSG_AUTH_DOMAIN} [opus.local]: " LDAP_DOMAIN
	LDAP_DOMAIN="${LDAP_DOMAIN:-opus.local}"

	local LDAP_BASE_DN
	LDAP_BASE_DN=$(echo "$LDAP_DOMAIN" | sed 's/\./,DC=/g; s/^/DC=/')

	local LDAP_USER_DN="CN=Users,${LDAP_BASE_DN}"

	# --- Project name → bind_dn ---
	read -rp "  ${MSG_AUTH_PROJECT}: " LDAP_PROJECT

	if [ -z "$LDAP_PROJECT" ]; then
		echo -e "  ${RED}[ERROR]${NC} ${MSG_AUTH_PROJECT_EMPTY}"
		exit 1
	fi

	local LDAP_BIND_DN="CN=${LDAP_PROJECT}-reader,${LDAP_USER_DN}"

	# --- CA cert ---
	read -rp "  ${MSG_AUTH_CA_CERT} [/usr/local/share/ca-certificates/opus-ca.crt]: " LDAP_CA_CERT
	LDAP_CA_CERT="${LDAP_CA_CERT:-/usr/local/share/ca-certificates/opus-ca.crt}"

	# --- Bind password ---
	read -srp "  ${MSG_AUTH_BIND_PASS}: " LDAP_BIND_PASS
	echo ""

	if [ -z "$LDAP_BIND_PASS" ]; then
		echo -e "  ${RED}[ERROR]${NC} ${MSG_AUTH_BIND_PASS_EMPTY}"
		exit 1
	fi

	# --- Test LDAPS connection ---
	echo -e "\n ${YELLOW}[...]${NC} ${MSG_AUTH_CONN_TEST}"

	if ! LDAPTLS_CACERT="$LDAP_CA_CERT" ldapsearch \
		-H "$LDAP_HOST:$LDAP_PORT" \
		-D "$LDAP_BIND_DN" \
		-w "$LDAP_BIND_PASS" \
		-b "$LDAP_BASE_DN" \
		-s base "(objectClass=*)" dn > /dev/null 2>&1; then
		echo -e "  ${RED}[ERROR]${NC} ${MSG_AUTH_CONN_FAIL}"
		exit 1
	fi

	echo -e "  ${GREEN}[OK]${NC} ${MSG_AUTH_CONN_OK}"

	# --- Build group_map from DB ---
	echo -e "\n ${YELLOW}[...]${NC} ${MSG_AUTH_GROUP_MAP}"

	local DB_NAME
	DB_NAME=$(jq -r '.storage[0] | keys[0]' "$GLOBAL_JSON")

	local GROUP_MAP="{}"
	local GNAME

	while IFS= read -r GNAME; do
		local AD_GROUP="${LDAP_PROJECT}-$(echo "$GNAME" | tr '[:upper:]' '[:lower:]')"
		GROUP_MAP=$(echo "$GROUP_MAP" | jq --arg ad "$AD_GROUP" --arg db "$GNAME" '. + {($ad): $db}')
	done < <(PGPASSWORD="$PG_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U postgres -d "$DB_NAME" -t -A -c "SELECT gname FROM groups.groups ORDER BY glevel DESC;")

	echo -e "  ${GREEN}[OK]${NC} ${MSG_AUTH_GROUP_MAP_OK}"

	# --- Write auth to global.json ---
	jq \
		--arg host "$LDAP_HOST" \
		--argjson port "$LDAP_PORT" \
		--arg base_dn "$LDAP_BASE_DN" \
		--arg user_dn "$LDAP_USER_DN" \
		--arg bind_dn "$LDAP_BIND_DN" \
		--arg ca_cert "$LDAP_CA_CERT" \
		--argjson group_map "$GROUP_MAP" \
		'.auth = {driver: "ldap", host: $host, port: $port, base_dn: $base_dn, user_dn: $user_dn, bind_dn: $bind_dn, ca_cert: $ca_cert, group_map: $group_map}' \
		"$GLOBAL_JSON" > "${GLOBAL_JSON}.tmp" && mv "${GLOBAL_JSON}.tmp" "$GLOBAL_JSON"

	fix_global_perms "$GLOBAL_JSON"

	# --- Write bind_password to local.json ---
	jq \
		--arg pass "$LDAP_BIND_PASS" \
		'.auth = {bind_password: $pass}' \
		"$LOCAL_JSON" > "${LOCAL_JSON}.tmp" && mv "${LOCAL_JSON}.tmp" "$LOCAL_JSON"

	echo -e "  ${GREEN}[OK]${NC} ${MSG_AUTH_OK}"
}

fix_global_perms() {
	chmod 0660 "$1"
	chgrp www-data "$1"
	[ -n "$SUDO_USER" ] && chown "${SUDO_USER}" "$1"
}
