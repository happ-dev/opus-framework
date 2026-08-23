<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-02-18 11:33:40
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-06-11 18:24:47
 **/

namespace Opus\view\layout;

use Opus\config\Config;
use Opus\html\form\Form;
use Opus\html\buttons\Buttons;
use Opus\view\navbar\Navbar;
use Opus\view\login\Login;
use Opus\controller\TraitController;

class Layout
{
	use TraitController;

	public ?string $opusMainAppScript;
	public ?string $appScript;
	protected ?object $navbar;
	protected ?string $loginForm;

	public function __construct(protected mixed $content = null, protected object $layout)
	{
		$this->setHeadLibs();
		$this->opusMainAppScript = $this->buildScript(
			$this->layout->js,
			fn($c) => "\nwindow.APP_LANGUAGE = '" . $_SESSION['lang'] . "';{$c}\n\t\t"
		);
		$this->appScript = $this->buildScript(
			$this->layout->appJs,
			fn($c) => "$(document).ready(function () {\n{$c}\n\t\t});"
		);
		$this->layout->navbar->closeButtonX = $this->offcanvasCloseButton();
		$this->navbar = Navbar::getInstance()->appsNavbar()->userNavbar();
		$this->loginForm = match (Config::getConfig('navbar')->login_form === true && $_SESSION['logged'] === false) {
			true => (function () {
				$login = new Login();
				return $login->modal->getModalByName('nav-opus-login');
			})(),
			false => null
		};

		return require_once $layout->index;
	}

	/**
	 * Generates HTML tags for vendor stylesheets, scripts, and Opus library files
	 *
	 * Builds <link> tags for CSS files and <script> tags for JS files defined in
	 * global config vendor array, appending Opus CSS, opus.js and {app}.lib.js if available.
	 * Results are stored in $this->layout->stylesheets and $this->layout->scripts.
	 *
	 * @return void
	 */
	private function setHeadLibs(): void
	{
		$globalVendor = Config::getConfig()->vendor ?? [];
		$appVendor = $this->layout->appVendor ?? [];

		$cssFiles = array_filter(array_merge(
			preg_grep('/\.css$/i', $globalVendor),
			preg_grep('/\.css$/i', $appVendor),
			[$this->layout->opusCss],
			[$this->layout->appCss]
		));

		$jsFiles = array_filter(array_merge(
			preg_grep('/\.js$/i', $globalVendor),
			[$this->layout->opusLib],
			preg_grep('/\.js$/i', $appVendor),
			[$this->layout->appLib]
		));

		$this->layout->stylesheets = implode(PHP_EOL, array_map(
			fn($f) => "<link rel=\"stylesheet\" href=\"vendor/{$f}\"/>", $cssFiles
		)) . PHP_EOL;

		$this->layout->scripts = implode(PHP_EOL, array_map(
			fn($f) => "<script type=\"text/javascript\" src=\"vendor/{$f}\"></script>", $jsFiles
		)) . PHP_EOL;
	}

	/**
	 * Includes a JS file, strips comments, and optionally wraps the content
	 *
	 * Captures the output of the included file via output buffering,
	 * removes comments using JS_PATTERN_COMMENTS, and applies the wrapper
	 * callable if provided.
	 *
	 * @param string|null $file Path to the JavaScript file to include
	 * @param callable|null $wrapper Optional callback that receives the cleaned content and returns wrapped string
	 * @return string|null The processed (and optionally wrapped) JS content, or null if empty
	 */
	private function buildScript(?string $file, ?callable $wrapper = null): ?string
	{
		// Start output buffering to capture included content
		ob_start();

		// Include main app js file
		require_once $file;

		// Get captured content and clean up
		$content = trim(ob_get_clean());

		if (empty($content)) {
			return null;
		}

		$content = trim(preg_replace(
			self::JS_PATTERN_COMMENTS,
			self::JS_REPLACEMENT_COMMENTS,
			$content
		));

		return $wrapper ? $wrapper($content) : $content;
	}

	private function offcanvasCloseButton(): string
	{
		$form = new Form();
		$form->addElement(Buttons::closeButtonX(
			'opus-nav-offcanvas',
			[
				'data-bs-dismiss' => 'offcanvas',
				'aria-label' => 'Close',
				'data-bs-target' => '#id__opus-nav-offcanvas'
			]
		));

		return $form->getElement('close-btn-opus-nav-offcanvas');
	}
}
