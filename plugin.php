<?php
/**
 * Plugin Name:       AI Provider for Ask Sage
 * Plugin URI:        https://github.com/wpvip/ai-provider-for-ask-sage
 * Description:       Ask Sage provider for the WordPress AI Client. FedRAMP High / IL-authorized GenAI with dataset grounding and personas.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.1.1
 * Author:            WordPress VIP
 * Author URI:        https://wpvip.com
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       ai-provider-for-ask-sage
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_PROVIDER_FOR_ASK_SAGE_MIN_PHP_VERSION', '7.4' );
define( 'AI_PROVIDER_FOR_ASK_SAGE_MIN_WP_VERSION', '7.0' );
define( 'AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_FILE', __FILE__ );

/**
 * Displays an admin notice for requirement failures.
 *
 * @since 1.0.0
 *
 * @param string $message The error message to display.
 */
function requirement_notice( string $message ): void {
	if ( ! is_admin() ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><?php echo esc_html( $message ); ?></p>
	</div>
	<?php
}

/**
 * Checks if the PHP version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @return bool True if PHP version is sufficient.
 */
function check_php_version(): bool {
	if ( version_compare( PHP_VERSION, AI_PROVIDER_FOR_ASK_SAGE_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'The Ask Sage Provider plugin requires PHP version %1$s or higher. You are running PHP version %2$s.', 'ai-provider-for-ask-sage' ),
						AI_PROVIDER_FOR_ASK_SAGE_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);
		return false;
	}
	return true;
}

/**
 * Checks if the WordPress version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @global string $wp_version WordPress version.
 *
 * @return bool True if WordPress version is sufficient.
 */
function check_wp_version(): bool {
	if ( ! is_wp_version_compatible( AI_PROVIDER_FOR_ASK_SAGE_MIN_WP_VERSION ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				requirement_notice(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'The Ask Sage Provider plugin requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'ai-provider-for-ask-sage' ),
						AI_PROVIDER_FOR_ASK_SAGE_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);
		return false;
	}
	return true;
}

/**
 * Registers a PSR-4 autoloader for the plugin namespace.
 *
 * Prefers the Composer autoloader when the plugin is installed as a package;
 * falls back to a minimal PSR-4 loader so the plugin also works as a plain drop-in.
 *
 * @since 1.0.0
 */
function register_autoloader(): void {
	if ( file_exists( AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		require_once AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR . 'vendor/autoload.php';
		return;
	}

	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = __NAMESPACE__ . '\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Loads the Ask Sage provider plugin.
 *
 * @since 1.0.0
 */
function load(): void {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	if ( ! check_php_version() || ! check_wp_version() ) {
		return;
	}

	register_autoloader();

	$plugin = new Plugin();
	$plugin->init();

	$loaded = true;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\load' );
