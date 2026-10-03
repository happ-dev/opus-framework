#!/bin/bash
#
# @Project: opus-skeleton
# @Version: v1.0.0
# @Author: Tomasz Ułazowski
# @Date:   2026-08-29 12:41:07
# @Last Modified by:   Tomasz Ułazowski
# @Last Modified time: 2026-09-24 19:42:08
#
# sed -i -e 's/\r$//' /opt/happ/v1.0.0/apps/klayr-cockpit/bash/lang/pl_install.sh
# chmod -R 774 /opt/happ/v1.0.0/apps/klayr-cockpit/bash/lang/pl_install.sh
#!/bin/bash
# Polish language file for install.sh

MSG_HEADER="=== Opus Instalacja ==="
MSG_DB_HOST="Host bazy danych"
MSG_DB_PORT="Port bazy danych"
MSG_PG_PASS="Hasło użytkownika postgres"
MSG_PG_PASS_EMPTY="Hasło postgres nie może być puste."
MSG_CONN_TEST="Test połączenia z PostgreSQL..."
MSG_CONN_OK="Połączenie z PostgreSQL nawiązane."
MSG_CONN_FAIL="Nie można połączyć się z PostgreSQL"
MSG_DIR_CHECK="Sprawdzanie katalogów..."
MSG_DIR_CREATED="UTWORZONO"
MSG_DIR_FIXED="NAPRAWIONO"
MSG_DIR_OK="OK"
MSG_PASS_GEN="Generowanie haseł..."
MSG_PASS_OPUS_ADMIN="Hasło dla opus_admin"
MSG_PASS_OPUS_USER="Hasło dla opus_user"
MSG_PASS_ROOT="Hasło dla root"
MSG_PASS_API="Hasło dla api"
MSG_PASS_CONFIRM="Powtórz hasło"
MSG_PASS_MISMATCH="Hasła nie są zgodne. Spróbuj ponownie."
MSG_CFG_CREATE="Tworzenie local.json..."
MSG_CFG_OK="local.json utworzony."
MSG_CFG_GLOBAL_MISSING="Nie znaleziono config/global.json."
MSG_SQL_EXEC="Wykonywanie SQL..."
MSG_SQL_OK="OK"
MSG_SQL_FAIL="BŁĄD"
MSG_SQL_SKIP="POMINIĘTO"
MSG_SECRET_SAVE="Zapisywanie haseł..."
MSG_SECRET_OK="Hasła zapisane w"
MSG_SECRET_WARN="UWAGA: Usuń ten plik po zapamiętaniu haseł!"

# --- Auth ---
MSG_AUTH_CONFIG="Konfiguracja autentykacji..."
MSG_AUTH_DRIVER="Sterownik auth (local/ldap)"
MSG_AUTH_INVALID_DRIVER="Nieprawidłowy sterownik auth. Dozwolone: local, ldap."
MSG_AUTH_HOST="Host LDAP"
MSG_AUTH_PORT="Port LDAP"
MSG_AUTH_DOMAIN="Domena"
MSG_AUTH_PROJECT="Nazwa projektu"
MSG_AUTH_PROJECT_EMPTY="Nazwa projektu nie może być pusta."
MSG_AUTH_CA_CERT="Ścieżka certyfikatu CA"
MSG_AUTH_BIND_PASS="Hasło bind"
MSG_AUTH_BIND_PASS_EMPTY="Hasło bind nie może być puste."
MSG_AUTH_CONN_TEST="Test połączenia LDAPS..."
MSG_AUTH_CONN_OK="Połączenie LDAPS nawiązane."
MSG_AUTH_CONN_FAIL="Nie można połączyć się z serwerem LDAP."
MSG_AUTH_GROUP_MAP="Budowanie mapy grup z bazy danych..."
MSG_AUTH_GROUP_MAP_OK="Mapa grup utworzona."
MSG_AUTH_OK="Konfiguracja auth zapisana."

# --- Reset ---
MSG_RESET_HEADER="=== Opus Reset ==="
MSG_RESET_DB_NAME="Nazwa bazy danych"
MSG_RESET_ROLE="Nazwa roli"
MSG_RESET_WILL_DELETE="Następujące elementy zostaną TRWALE usunięte"
MSG_RESET_CONFIRM="Wpisz 'RESET' aby potwierdzić"
MSG_RESET_ABORT="Przerwano."
MSG_RESET_DROP_DB="Usuwanie bazy danych:"
MSG_RESET_DROP_ROLES="Usuwanie ról..."
MSG_RESET_FILES="Usuwanie plików..."
MSG_RESET_DONE="Reset zakończony."
MSG_RESET_REINSTALL="Uruchom 'sudo ./install.sh' aby zainstalować ponownie."
MSG_RESET_AUTH="Usuwanie konfiguracji auth..."
