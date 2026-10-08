<?php

declare(strict_types=1);

namespace QBitFlow;

/**
 * Default configuration for the QBitFlow SDK.
 *
 * The defaults may be overridden per client instance (see {@see QBitFlow::__construct()})
 * or globally through the `QBITFLOW_BASE_URL` environment variable.
 */
final class Config
{
	/** Default base URL for the QBitFlow API. */
	public const DEFAULT_BASE_URL = 'https://api.qbitflow.app/v1';

	/** API version covered by this SDK. */
	public const API_VERSION = 'v1';

	/**
	 * Default request timeout, in **seconds**.
	 *
	 * Note for users coming from the JavaScript SDK: that SDK expresses the timeout in
	 * milliseconds. PHP HTTP clients work in seconds, so this SDK does too.
	 */
	public const DEFAULT_TIMEOUT = 30;

	/** Default maximum number of retry attempts for failed requests. */
	public const DEFAULT_MAX_RETRIES = 3;

	/** Base delay between retries, in seconds. Grows linearly with each attempt. */
	public const DEFAULT_RETRY_DELAY = 1.0;

	/**
	 * Resolve the base URL, honouring the `QBITFLOW_BASE_URL` environment variable.
	 */
	public static function baseUrl(): string
	{
		$fromEnv = getenv('QBITFLOW_BASE_URL');

		if (is_string($fromEnv) && $fromEnv !== '') {
			return rtrim($fromEnv, '/');
		}

		return self::DEFAULT_BASE_URL;
	}
}
