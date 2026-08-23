# Configuration

> [!IMPORTANT]
> In the future all configuration files will become true as soon as you write Hello World.

## public/index.php

```php
chdir(dirname(__DIR__));
$autoload = 'vendor/autoload.php';

try {
	// test if autoload file exist
	file_exists($autoload) ?: throw new Exception($autoload . ' file could not be found.');

	// autoload composer
	require_once $autoload;

	// load configuration
	Opus\config\Config::loadConfiguration();

	// session start
	Opus\controller\Controller::session();

	// start application
	Opus\controller\Controller::run();
} catch (Exception $ex) {
	echo $ex->getMessage() . PHP_EOL;
}
```


