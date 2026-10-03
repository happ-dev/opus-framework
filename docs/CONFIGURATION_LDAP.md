# LDAP Authentication — Samba DC Preparation

Konfiguracja Samba DC wymagana aby Opus Framework mógł autentykować użytkowników przez LDAPS.

> **Wymaganie:** Instrukcja zakłada poprawnie skonfigurowane Samba AD DC z LDAPS (TLS) — patrz [03 – Instalacja i Konfiguracja Samba DC](../../home-network/docs/03-ct-dc-srv-1.md).

Szczegóły implementacji po stronie frameworka — patrz [PLAN_LdapAuth.md](PLAN_LdapAuth.md).

---

## 1. Service account (read-only)

Opus potrzebuje konta serwisowego do przeszukiwania katalogu LDAP (wyszukanie użytkownika przed bind). Konto nie wymaga uprawnień zapisu.

```bash
sudo samba-tool user create {project-name}-reader --description="Opus LDAP read-only service account"
sudo samba-tool user setexpiry {project-name}-reader --noexpiry
```

> **Uwaga:** Hasło tego konta będzie przechowywane zaszyfrowane w `local.json` na serwerze www. Użyć silnego hasła.

---

## 2. Grupy Opus

Grupy mapowane na poziomy dostępu w aplikacji Opus.

```bash
sudo samba-tool group add {project-name}-root --description="Full portal access, setup panel and all apps|opus.group.root"
sudo samba-tool group add {project-name}-admins --description="User and app configuration management|opus.group.admins"
sudo samba-tool group add {project-name}-powerusers --description="Extended access, content editing and own settings|opus.group.powerusers"
sudo samba-tool group add {project-name}-users --description="Standard portal app access|opus.group.users"
sudo samba-tool group add {project-name}-api --description="API-only access for service accounts|opus.group.api"
sudo samba-tool group add {project-name}-guests --description="Read-only access, no editing|opus.group.guests"
sudo samba-tool group add {project-name}-public --description="Public content, no login required|opus.group.public"
sudo samba-tool group add {project-name}-banned --description="Blocked account, access denied|opus.group.banned"
```

| Grupa AD | Grupa Opus (`groups.groups`) | Poziom | Opis |
| :--- | :--- | :--- | :--- |
| `{project-name}-root` | `Root` | 9 | Full portal access, setup panel and all apps |
| `{project-name}-admins` | `Admin` | 7 | User and app configuration management |
| `{project-name}-powerusers` | `PowerUsers` | 5 | Extended access, content editing and own settings |
| `{project-name}-users` | `Users` | 3 | Standard portal app access |
| `{project-name}-api` | `Api` | 2 | API-only access for service accounts |
| `{project-name}-guests` | `Guests` | 1 | Read-only access, no editing |
| `{project-name}-public` | `Public` | 0 | Public content, no login required |
| `{project-name}-banned` | `Banned` | -1 | Blocked account, access denied |

Przypisanie użytkownika:

```bash
sudo samba-tool group addmembers {project-name}-admins <nazwa_uzytkownika>
openssl s_client -connect dc.opus.local:636 -CAfile /usr/local/share/ca-certificates/opus-ca.crt
```

> **Uwaga:** Użytkownik może należeć do wielu grup — Opus wybierze najwyższy poziom dostępu.

---

## 3. Dystrybucja certyfikatu CA

Serwer www (`ct-www-srv-1`) musi ufać CA aby połączyć się z LDAPS. Skopiować certyfikat z DC:

```bash
# Na ct-www-srv-1:
sudo scp <nazwa_uzytkownika>@ct-dc-srv-1:/etc/samba/tls/ca-cert.pem /usr/local/share/ca-certificates/opus-ca.crt
sudo update-ca-certificates
```

Konfiguracja klienta LDAP — edycja `/etc/ldap/ldap.conf`:

```text
TLS_CACERT /usr/local/share/ca-certificates/opus-ca.crt
```

Ścieżka do CA w konfiguracji Opus: `/usr/local/share/ca-certificates/opus-ca.crt`

---

## 4. Weryfikacja LDAPS z serwera www

```bash
# Na ct-www-srv-1 (wymaga pakietu ldap-utils):
sudo apt install -y ldap-utils

ldapsearch -H ldaps://dc.opus.local:636 \
  -D "CN={project-name}-reader,CN=Users,DC=opus,DC=local" \
  -W \
  -b "CN=Users,DC=opus,DC=local" \
  "(sAMAccountName=<nazwa_uzytkownika>)" \
  sn givenName mail memberOf
```

Powinno zwrócić atrybuty użytkownika i listę grup (`memberOf`).

---

## 5. Wymagania na serwerze www

```bash
# Na ct-www-srv-1:
sudo apt install -y php-ldap
sudo systemctl restart apache2
```

Weryfikacja:

```bash
php -m | grep ldap
```
