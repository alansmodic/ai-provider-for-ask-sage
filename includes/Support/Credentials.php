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
	 * Environment variable and PHP constant name for the instance endpoint.
	 *
	 * @since 1.1.1
	 * @var string
	 */
	public const BASE_URL_CONSTANT = 'ASK_SAGE_BASE_URL';

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
	 * On WordPress VIP, prefer an environment variable or PHP constant over the Connectors
	 * option so the secret is not stored in the database.
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

		if ( defined( self::KEY_CONSTANT ) ) {
			$constant = constant( self::KEY_CONSTANT );
			if ( is_string( $constant ) && '' !== $constant ) {
				return $constant;
			}
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
	 * Invalid or non-HTTPS configured URLs fail closed (empty string) rather than
	 * falling back to the commercial endpoint, so a misconfigured GovCloud tenant
	 * cannot silently send prompts to api.asksage.ai.
	 *
	 * @since 1.0.0
	 *
	 * @return string The base URL without a trailing slash, or an empty string when invalid.
	 */
	public static function base_url(): string {
		$configured = self::configured_base_url();
		if ( null === $configured ) {
			return self::DEFAULT_BASE_URL;
		}

		return self::normalize_base_url( $configured );
	}

	/**
	 * Returns the explicitly configured base URL, if any.
	 *
	 * @since 1.1.1
	 *
	 * @return string|null The configured URL, or null when unset.
	 */
	private static function configured_base_url(): ?string {
		if ( defined( self::BASE_URL_CONSTANT ) ) {
			$constant = constant( self::BASE_URL_CONSTANT );
			if ( is_string( $constant ) && '' !== $constant ) {
				return $constant;
			}
		}

		$env = getenv( self::BASE_URL_CONSTANT );
		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		return null;
	}

	/**
	 * Validates and normalizes an Ask Sage instance URL.
	 *
	 * Only HTTPS URLs with a host and without embedded credentials are accepted.
	 *
	 * @since 1.1.1
	 *
	 * @param string $url The candidate URL.
	 * @return string The normalized URL, or an empty string when invalid.
	 */
	private static function normalize_base_url( string $url ): string {
		$url = rtrim( trim( $url ), '/' );

		if ( function_exists( 'wp_http_validate_url' ) ) {
			$validated = wp_http_validate_url( $url );
			if ( false === $validated ) {
				return '';
			}
			$url = rtrim( (string) $validated, '/' );
		}

		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( $url );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Non-WordPress package fallback.
			$parts = parse_url( $url );
		}
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return '';
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}

		return $url;
	}
}
