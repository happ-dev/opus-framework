<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-08-28 20:00:00
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-08-28 20:00:00
 **/

namespace Opus\controller\security;

class Csp
{
	private static string $nonce;

	public static function send(): void
	{
		self::$nonce = bin2hex(random_bytes(16));
		header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . self::$nonce . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'");
	}

	public static function getNonce(): string
	{
		return self::$nonce;
	}
}
