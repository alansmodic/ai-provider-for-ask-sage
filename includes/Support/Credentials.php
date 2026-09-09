<?php
/**
 * Credential and endpoint resolution.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the Ask Sage API key and instance endpoint.
 *
 * @since 1.0.0
 */
class Credentials {

	/**
	 * Provider ID. Core derives the connector setting and constant names from this.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const PROVIDER_ID = 'ask_sage';

	/**
	 * Option name Core registers for this connector.
	 *
	 * Core builds this as "connectors_ai_{sanitized_id}_api_key" when it auto-generates
	 * the connector entry from the registered provider's metadata.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_NAME = 'connectors_ai_ask_sage_api_key';

	/**
	 * Environment variable and PHP constant name.
	 *
	 * Core derives this as "{CONSTANT_CASE_ID}_API_KEY".
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const KEY_CONSTANT = 'ASK_SAGE_API_KEY';

	/**
	 * Default commercial endpoint. Gov tenants override via ASK_SAGE_BASE_URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const DEFAULT_BASE_URL = 'https://api.asksage.ai';

	/**
	 * Resolves the API key, matching Core's precedence: env var, constant, then option.
	 *
	 * Ask Sage accepts its static API key directly in the x-access-tokens header, so no
	 * token exchange is required and the key fits Core's api_key authentication model.
	 *
	 * @since 1.0.0
	 *
	 * @return string The API key, or an empty string when not configured.
	 */
	public static function api_key(): string {
		$env = getenv( self::KEY_CONSTANT );
		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		if ( defined( self::KEY_CONSTANT ) && constant( self::KEY_CONSTANT ) ) {
			return (string) constant( self::KEY_CONSTANT );
		}

		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		$option = get_option( self::OPTION_NAME, '' );

		return is_string( $option ) ? $option : '';
	}

	/**
	 * Resolves the Ask Sage instance base URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The base URL without a trailing slash.
	 */
	public static function base_url(): string {
		if ( defined( 'ASK_SAGE_BASE_URL' ) && ASK_SAGE_BASE_URL ) {
			return rtrim( (string) ASK_SAGE_BASE_URL, '/' );
		}

		$env = getenv( 'ASK_SAGE_BASE_URL' );
		if ( is_string( $env ) && '' !== $env ) {
			return rtrim( $env, '/' );
		}

		return self::DEFAULT_BASE_URL;
	}
}
