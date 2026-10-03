# LDAP Authentication — Samba DC Preparation

Samba DC configuration required for Opus Framework to authenticate users via LDAPS.

> **Requirement:** This guide assumes a properly configured Samba AD DC with LDAPS (TLS) — see [03 – Samba DC Installation and Configuration](../../home-network/docs/03-ct-dc-srv-1.md).

For framework-side implementation details — see [PLAN_LdapAuth.md](PLAN_LdapAuth.md).

---

## 1. Service account (read-only)

Opus needs a service account to search the LDAP directory (user lookup before bind). The account does not require write permissions.

```bash
sudo samba-tool user create {project-name}-reader --description="Opus LDAP read-only service account"
sudo samba-tool user setexpiry {project-name}-reader --noexpiry
```

> **Note:** The password for this account will be stored encrypted in `local.json` on the web server. Use a strong password.

---

## 2. Opus groups

Groups mapped to access levels in the Opus application.

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

| AD Group | Opus Group (`groups.groups`) | Level | Description |
| :--- | :--- | :--- | :--- |
| `{project-name}-root` | `Root` | 9 | Full portal access, setup panel and all apps |
| `{project-name}-admins` | `Admin` | 7 | User and app configuration management |
| `{project-name}-powerusers` | `PowerUsers` | 5 | Extended access, content editing and own settings |
| `{project-name}-users` | `Users` | 3 | Standard portal app access |
| `{project-name}-api` | `Api` | 2 | API-only access for service accounts |
| `{project-name}-guests` | `Guests` | 1 | Read-only access, no editing |
| `{project-name}-public` | `Public` | 0 | Public content, no login required |
| `{project-name}-banned` | `Banned` | -1 | Blocked account, access denied |

Adding a user to a group:

```bash
sudo samba-tool group addmembers {project-name}-admins <username>
openssl s_client -connect dc.opus.local:636 -CAfile /usr/local/share/ca-certificates/opus-ca.crt
```

> **Note:** A user can belong to multiple groups — Opus will select the highest access level.

---

## 3. CA certificate distribution

The web server (`ct-www-srv-1`) must trust the CA to connect via LDAPS. Copy the certificate from the DC:

```bash
# On ct-www-srv-1:
sudo scp <username>@ct-dc-srv-1:/etc/samba/tls/ca-cert.pem /usr/local/share/ca-certificates/opus-ca.crt
sudo update-ca-certificates
```

LDAP client configuration — edit `/etc/ldap/ldap.conf`:

```text
TLS_CACERT /usr/local/share/ca-certificates/opus-ca.crt
```

CA path in Opus configuration: `/usr/local/share/ca-certificates/opus-ca.crt`

---

## 4. LDAPS verification from the web server

```bash
# On ct-www-srv-1 (requires ldap-utils package):
sudo apt install -y ldap-utils

ldapsearch -H ldaps://dc.opus.local:636 \
  -D "CN={project-name}-reader,CN=Users,DC=opus,DC=local" \
  -W \
  -b "CN=Users,DC=opus,DC=local" \
  "(sAMAccountName=<username>)" \
  sn givenName mail memberOf
```

Should return user attributes and group list (`memberOf`).

---

## 5. Web server requirements

```bash
# On ct-www-srv-1:
sudo apt install -y php-ldap
sudo systemctl restart apache2
```

Verification:

```bash
php -m | grep ldap
```
