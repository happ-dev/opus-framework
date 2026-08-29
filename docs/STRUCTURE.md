# Project Structure

> [!IMPORTANT]
> __{{app_name}}__.* means that this file is required

## Opus directory

```
- Opus/
	- apps/
		- __{{app_name}}__/
			- config/			> __{{app_name}}__.config.json
			- js/
			- libs/
			- sql/
			- src/				> __{{App_name}}__Controller.php
			- view/				> __{{app_name}}__Controller.phtml
			- files/
				- public/
				- private/
		- skeleton/
		- settings/
		- profile/
		- logs/
		- demo/
	- bin/
		- install.sh
		- reset.sh
		- lang/
			- en_install.sh
			- pl_install.sh
		- scripts/
			- step_connection.sh
			- step_directories.sh
			- step_passwords.sh
			- step_config.sh
			- step_sql.sh
			- step_secret.sh
	- config/
	- controller/
		- auth/
		- cli/
		- event/
		- exception/
		- lang/
		- login/
		- query/
		- request/
		- security/
	- html/
		- asyncpage/
		- form/					> moved from controller/
		- table/				> moved from controller/
		- buttons/				> moved from view/
		- collapse/				> moved from view/
		- modal/				> moved from view/
		- offcanvas/			> moved from view/
		- sidebar/				> moved from view/
	- js/
	- libs/						> before controller/common/
	- sql/
	- storage/
		- curl/
		- db/
		- exception/
		- json/
	- view/
		- layout/
		- navbar/
		- login/
			- Login.php			> moved from view/navbar/ and renamed from LoginForm.php
		- view/
	- css/						> opus.css
- docs/
- .gitignore
- README.md
```

## Public directory

```
- public/
	- vendor/
		- opus/
			- opus.css
			- opus.js
			- opus.svg
			- __{{app_name}}.lib.js__
			- __{{app_name}}.js__
		- all additional libraries listed in global.json --> vendor
	- img/
	- var/
	- .htaccess
	- index.php
```
