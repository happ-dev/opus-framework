/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-22 19:53:51
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-10-03 12:46:04
**/

-- =============================================================================
-- Schema: users
-- Description: User management - authentication, authorization, session data
-- =============================================================================
CREATE SCHEMA "users";
COMMENT ON SCHEMA "users" IS 'User management schema - authentication, authorization, roles, permissions, and session data';

-- Grants: schema
GRANT ALL PRIVILEGES ON SCHEMA "users" TO "opus_admin";
GRANT USAGE ON SCHEMA "users" TO "opus_user";

-- =============================================================================
-- Table: users.users
-- Description: Core user accounts table with credentials and contact information
-- =============================================================================
CREATE TABLE IF NOT EXISTS "users"."users" (
	id__users 		SERIAL,
	login 			CHARACTER VARYING(10) UNIQUE NOT NULL,
	ulevel 			SMALLINT NOT NULL,
	active 			BOOLEAN NOT NULL,
	auth_type 		CHARACTER VARYING(5) NOT NULL DEFAULT 'local',
	"password" 		CHARACTER VARYING(64) NOT NULL,
	lastname 		CHARACTER VARYING(32) NOT NULL,
	firstname 		CHARACTER VARYING(32) NOT NULL,
	email 			CHARACTER VARYING(64),
	homephone 		CHARACTER VARYING(12),
	cellphone 		CHARACTER VARYING(12),
	lang 			CHARACTER VARYING(2),
	CONSTRAINT pk_users PRIMARY KEY (id__users)
);

-- Indexes: users.users
CREATE INDEX idx_login ON users.users (login);
CREATE INDEX idx_ulevel ON users.users (ulevel);

-- Grants: users.users
GRANT SELECT, INSERT, UPDATE ON TABLE "users"."users" TO opus_user;
GRANT SELECT, UPDATE ON SEQUENCE "users"."users_id__users_seq" TO opus_user;

-- Comments: users.users
COMMENT ON COLUMN users.users.id__users IS 'Identifier|opus.db.users.id';
COMMENT ON COLUMN users.users.login IS 'Username|opus.db.users.login';
COMMENT ON COLUMN users.users.ulevel IS 'Access level|opus.db.users.ulevel';
COMMENT ON COLUMN users.users.active IS 'Account active?|opus.db.users.active';
COMMENT ON COLUMN users.users.password IS 'Password|opus.db.users.password';
COMMENT ON COLUMN users.users.lastname IS 'Last name|opus.db.users.lastname';
COMMENT ON COLUMN users.users.firstname IS 'First name|opus.db.users.firstname';
COMMENT ON COLUMN users.users.email IS 'E-mail address|opus.db.users.email';
COMMENT ON COLUMN users.users.homephone IS 'Home phone|opus.db.users.homephone';
COMMENT ON COLUMN users.users.cellphone IS 'Cell phone|opus.db.users.cellphone';
COMMENT ON COLUMN users.users.lang IS 'Default language|opus.db.users.lang';
COMMENT ON COLUMN users.users.auth_type IS 'Auth source|opus.db.users.auth_type';

-- =============================================================================
-- Data: users.users
-- Description: Initial user accounts (root, api)
-- =============================================================================
BEGIN TRANSACTION;

INSERT INTO users.users(
	login, ulevel, active, "password", lastname, firstname, email, homephone, cellphone, lang
) VALUES (
	'root',
	(SELECT DISTINCT glevel FROM "groups"."groups" WHERE gname = 'Root'),
	TRUE,
	'$root_password$',
	'root',
	'root',
	'root@opus.local',
	NULL,
	NULL,
	'pl'
);

INSERT INTO users.users(
	login, ulevel, active, "password", lastname, firstname, email, homephone, cellphone, lang
) VALUES (
	'api',
	(SELECT DISTINCT glevel FROM "groups"."groups" WHERE gname = 'Api'),
	TRUE,
	'$api_password$',
	'api',
	'api',
	'api@opus.local',
	NULL,
	NULL,
	'en'
);

COMMIT;
--ROLLBACK;

-- =============================================================================
-- View: users.vgroups
-- Description: User-group mapping view (login, group name, access level)
-- =============================================================================
CREATE OR REPLACE VIEW users.vgroups AS
SELECT DISTINCT
	login, gname, ulevel
FROM users.users
	LEFT JOIN groups.groups ON (groups.glevel = users.ulevel);

-- Grants: users.vgroups
GRANT SELECT ON TABLE users.vgroups TO opus_user;

-- Comments: users.vgroups
COMMENT ON COLUMN users.vgroups.login IS 'Username|opus.db.vgroups.login';
COMMENT ON COLUMN users.vgroups.gname IS 'Group name|opus.db.vgroups.gname';
COMMENT ON COLUMN users.vgroups.ulevel IS 'Access level|opus.db.vgroups.ulevel';

-- =============================================================================
-- View: users.userdata
-- Description: Complete user data view with group name (joined from groups schema)
-- =============================================================================
CREATE OR REPLACE VIEW users.userdata AS
SELECT DISTINCT
	"id__users", "login", "ulevel", "gname", "active", "password", "lastname", "firstname", "email", "homephone", "cellphone", "lang", "auth_type"
FROM users.users
	LEFT JOIN groups.groups ON (groups.glevel = users.ulevel);

-- Grants: users.userdata
GRANT SELECT ON TABLE users.userdata TO opus_user;

-- Comments: users.userdata
COMMENT ON COLUMN users.userdata.id__users IS 'Identifier|opus.db.userdata.id';
COMMENT ON COLUMN users.userdata.login IS 'Username|opus.db.userdata.login';
COMMENT ON COLUMN users.userdata.ulevel IS 'Access level|opus.db.userdata.ulevel';
COMMENT ON COLUMN users.userdata.gname IS 'Group name|opus.db.userdata.gname';
COMMENT ON COLUMN users.userdata.active IS 'Account active?|opus.db.userdata.active';
COMMENT ON COLUMN users.userdata.password IS 'Password|opus.db.userdata.password';
COMMENT ON COLUMN users.userdata.lastname IS 'Last name|opus.db.userdata.lastname';
COMMENT ON COLUMN users.userdata.firstname IS 'First name|opus.db.userdata.firstname';
COMMENT ON COLUMN users.userdata.email IS 'E-mail address|opus.db.userdata.email';
COMMENT ON COLUMN users.userdata.homephone IS 'Home phone|opus.db.userdata.homephone';
COMMENT ON COLUMN users.userdata.cellphone IS 'Cell phone|opus.db.userdata.cellphone';
COMMENT ON COLUMN users.userdata.lang IS 'Default language|opus.db.userdata.lang';
COMMENT ON COLUMN users.userdata.auth_type IS 'Auth source|opus.db.userdata.auth_type';
