<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * These exist so production code that optionally calls WordPress APIs can be
 * exercised without loading WordPress. They are not a complete WP polyfill.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

$GLOBALS['ai_provider_for_ask_sage_test_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Registers a filter callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $accepted_args );
		$GLOBALS['ai_provider_for_ask_sage_test_filters'][ $hook ][ (int) $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Applies registered filter callbacks.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Initial value.
	 * @param mixed  ...$args Additional arguments.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['ai_provider_for_ask_sage_test_filters'][ $hook ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['ai_provider_for_ask_sage_test_filters'][ $hook ] );
		foreach ( $GLOBALS['ai_provider_for_ask_sage_test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Returns a stable UUID for tests.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000000';
	}
}
