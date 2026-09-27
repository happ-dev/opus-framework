<?php

/**
 * @Project: opus-skeleton
 * @Version: v1.0.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-26 20:44:04
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-09-23 21:05:36
 **/

namespace Opus\apps\setup\src;

define('SETUP_ERROR_FILE', __DIR__ . '/../lang/' . $_SESSION['lang'] . '_error.json');

use Opus\controller\InterfaceCliController;
use Opus\controller\cli\CliArguments;
use Opus\controller\cli\CliColor;
use Opus\controller\exception\ControllerException;
use Opus\controller\lang\Lang;

class SetupController implements InterfaceCliController
{
	private const ASCII_ART = <<<'ART'

	   ____
	  / __ \____  __  _______
	 / / / / __ \/ / / / ___/
	/ /_/ / /_/ / /_/ (__  )
	\____/ .___/\__,_/____/
	    /_/

	ART;

	private array $arguments;

	public function cliAction(): void
	{
		$this->arguments = CliArguments::getArguments();

		echo match ($this->arguments[0] ?? '') {
			'welcome' => $this->welcome(),
			'check' => SetupCheck::doCheck(),
			'repair' => SetupRepair::doRepair(),
			'sql' => SetupSQL::doSQL($this->arguments),
			default => throw new ControllerException(
				'controller\cli\argument',
				['message' => [$this->arguments[0], 'setup']],
				ControllerException::TYPE_CLI_EXCEPTION
			)
		};
	}

	/**
	 * Displays welcome message after installation
	 * Side effect: forces EncryptStorageConfig to create secret.key and encrypt local.json
	 *
	 * @return string
	 */
	private function welcome(): string
	{
		$lang = Lang::getInstance();

		return CliColor::write(self::ASCII_ART, CliColor::COLOR_LIGHT_CYAN)
			. CliColor::write('  ' . $lang->get('setup.welcome'), CliColor::COLOR_LIGHT_GREEN)
			. PHP_EOL;
	}
}
