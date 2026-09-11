<?php
/**
 * PHPUnit bootstrap.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/dummy-abspath/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

require_once __DIR__ . '/wp-stubs.php';

$ai_provider_for_ask_sage_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $ai_provider_for_ask_sage_autoload ) ) {
	fwrite( STDERR, "Composer autoloader not found. Run composer install first.\n" );
	exit( 1 );
}

require_once $ai_provider_for_ask_sage_autoload;
