/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-22 19:57:51
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-29 15:15:16
**/

CREATE USER opus_admin
WITH
	PASSWORD '$opus_admin_password$' LOGIN SUPERUSER INHERIT CREATEDB NOCREATEROLE NOREPLICATION;

CREATE USER opus_user
WITH
	PASSWORD '$opus_user_password$' LOGIN NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;
