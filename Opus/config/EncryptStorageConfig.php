<?php

/**
 * @Project: Opus
 * @Version: 1.0
 * @Author: Tomasz Ułazowski
 * @Date:   2026-01-27 13:25:44
 * @Last Modified by:   Tomasz Ułazowski
 * @Last Modified time: 2026-02-07 17:04:05
 **/

namespace Opus\config;

use Exception;
use Opus\storage\json\Json;

class EncryptStorageConfig
{
	const OPUS_CIPHER = 'AES-256-CBC';
	protected string $encryptionKey;

	public function __construct()
	{
		$this->encryptionKey = $this->getOrCreateSecretKey();
	}

	/**
	 * Function loads or generates and saves to a file
	 * a key to decrypt the encrypted part of the configuration.
	 * Uses PBKDF2 for key derivation and includes salt.
	 *
	 * @return string $secretKey
	 * @throws Exception If file operations fail
	 */
	private function getOrCreateSecretKey(): string
	{
		try {
			// Return existing key if available
			if (file_exists(Config::OPUS_SECRET_KEY)) {
				$storedKey = file_get_contents(Config::OPUS_SECRET_KEY)
					?: throw new Exception('Failed to read secret key file');

				return (strlen($storedKey) === 96)
					? substr($storedKey, 32)
					: throw new Exception('Invalid key format');
			}

			// Generate new key
			$rawKey = random_bytes(64);
			$salt = random_bytes(32);
			$keyMaterial = hash_pbkdf2(
				'sha512',
				$rawKey,
				$salt,
				100000,
				64,
				true
			);

			// Store key with salt
			$storageKey = $salt . $keyMaterial;
			file_put_contents(Config::OPUS_SECRET_KEY, $storageKey, LOCK_EX)
				?: throw new Exception('Failed to write secret key file');

			chmod(Config::OPUS_SECRET_KEY, 0660);
			chgrp(Config::OPUS_SECRET_KEY, 'www-data');
			return $keyMaterial;
		} catch (Exception $e) {
			throw new Exception('Key operation failed: ' . $e->getMessage());
		}
	}

	/**
	 * Function encrypts the value
	 *
	 * @param ?string $value
	 * @return string|false
	 * @throws Exception if the encryption attempt fails
	 */
	private function encrypt(?string $value): string|false
	{
		if (empty($value)) {
			throw new Exception('Value cannot be empty or null');
		}

		try {
			$ivlen = openssl_cipher_iv_length(self::OPUS_CIPHER)
				?: throw new Exception('Invalid cipher method');

			$iv = openssl_random_pseudo_bytes($ivlen)
				?: throw new Exception('Failed to generate IV');

			$encrypted = openssl_encrypt(
				$value,
				self::OPUS_CIPHER,
				$this->encryptionKey,
				OPENSSL_RAW_DATA,
				$iv
			)
				?: throw new Exception(
					'Encryption failed: ' . (openssl_error_string() ?: 'Unknown error')
				);

			return base64_encode($iv . $encrypted);
		} catch (Exception $event) {
			throw new Exception('Encryption error: ' . $event->getMessage());
		}
	}

	/**
	 * Value decryption function
	 *
	 * @param ?string $value
	 * @return string|bool
	 * @throws Exception if the decryption attempt fails
	 */
	public static function decrypt(?string $value): string|bool
	{
		if (is_null($value)) {
			throw new Exception('Value cannot be null');
		}

		$decrypt = new self();

		try {
			// Validate and decode base64 input
			if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $value)) {
				throw new Exception('Input is not valid base64 format');
			}

			$encrypted = base64_decode($value, true)
				?: throw new Exception('Invalid base64 encoding');

			$ivlen = openssl_cipher_iv_length(self::OPUS_CIPHER)
				?: throw new Exception('Invalid cipher method');

			// Validate encrypted data length
			$minLength = $ivlen + 16;
			if (strlen($encrypted) < $minLength) {
				throw new Exception(sprintf(
					'Encrypted data too short. Minimum length: %d, Got: %d',
					$minLength,
					strlen($encrypted)
				));
			}

			// Extract IV and ciphertext
			$iv = substr($encrypted, 0, $ivlen);
			$ciphertext = substr($encrypted, $ivlen);

			if (strlen($iv) !== $ivlen) {
				throw new Exception('Invalid IV length');
			}

			// Decrypt the data
			// amazonq-ignore-next-line
			$decrypted = openssl_decrypt(
				$ciphertext,
				self::OPUS_CIPHER,
				$decrypt->encryptionKey,
				OPENSSL_RAW_DATA,
				$iv
			) ?: throw new Exception(
				'Decryption failed: ' . (openssl_error_string() ?: 'Unknown error')
			);

			return $decrypted;
		} catch (Exception $event) {
			throw new Exception(sprintf(
				'Decryption error: %s [Cipher: %s, Data length: %d]',
				$event->getMessage(),
				self::OPUS_CIPHER,
				strlen($encrypted ?? '')
			));
		}
	}

	/**
	 * Function checks if the value is encrypted
	 *
	 * @param ?string $value
	 * @return bool
	 */
	public static function isEncrypted(?string $value): bool
	{
		try {
			// Attempt to decrypt - if it fails, it wasn't encrypted by us
			self::decrypt($value);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Check and encrypt storage configuration parameters if not already encrypted
	 *
	 * @return bool Returns true if encryption was successful, false otherwise
	 * @throws Exception If file operations fail or encryption errors occur
	 */
	public static function encryptStorageConfig(): bool
	{
		try {
			$config = Json::loadJsonFile(Config::OPUS_LOCAL_CONFIG);

			if (!isset($config->storage) || !is_array($config->storage)) {
				return false;
			}

			$encryptStorageConfig = new self();
			$modified = false;

			// Process each storage configuration
			array_walk($config->storage, function (&$storageItem) use ($encryptStorageConfig, &$modified) {

				if (!is_object($storageItem)) {
					return;
				}

				// Process each provider in the storage item
				foreach ($storageItem as &$provider) {

					if (!is_object($provider)) {
						continue;
					}

					// Encrypt all string fields that aren't already encrypted
					foreach (get_object_vars($provider) as $field => $value) {

						if (!is_string($value) || self::isEncrypted($value)) {
							continue;
						}

						$provider->$field = $encryptStorageConfig->encrypt($value);
						$modified = true;
					}
				}
			});

			// Save changes if modifications were made
			if ($modified) {
				$jsonNewContent = json_encode(
					$config,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				) ?: throw new Exception('JSON encoding failed');

				file_put_contents(Config::OPUS_LOCAL_CONFIG, $jsonNewContent)
					?: throw new Exception('Failed to write to json file: ' . Config::OPUS_LOCAL_CONFIG);

				chmod(Config::OPUS_LOCAL_CONFIG, 0660);
				chgrp(Config::OPUS_LOCAL_CONFIG, 'www-data');
				return true;
			}

			return false;
		} catch (Exception $event) {
			throw new Exception('Encryption error: ' . $event->getMessage());
		}
	}
}
