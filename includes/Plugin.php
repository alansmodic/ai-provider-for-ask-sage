<?php
/**
 * Plugin initializer class.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage;

use WordPress\AiClient\AiClient;
use WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication;
use WordPressVIP\AiProviderForAskSage\Provider\AskSageProvider;
use WordPressVIP\AiProviderForAskSage\Support\Credentials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * The provider is registered early so Core can auto-generate the Settings > Connectors
	 * card from its metadata. Authentication is registered late, after Core passes stored
	 * connector keys to the AI Client at priority 20, so the Ask Sage header scheme wins.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_authentication' ), 25 );
		add_filter(
			'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Registers the Ask Sage provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( AskSageProvider::class ) ) {
			return;
		}

		$registry->registerProvider( AskSageProvider::class );
	}

	/**
	 * Registers Ask Sage's header-based authentication.
	 *
	 * Core registers ApiKeyRequestAuthentication (an "Authorization: Bearer" scheme) for any
	 * api_key connector. Ask Sage requires the x-access-tokens header instead, so this replaces
	 * it once a key is resolvable.
	 *
	 * @since 1.0.0
	 */
	public function register_authentication(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$api_key = Credentials::api_key();
		if ( '' === $api_key ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( Credentials::PROVIDER_ID ) ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			Credentials::PROVIDER_ID,
			new AccessTokenAuthentication( $api_key )
		);
	}

	/**
	 * Adds a settings link to the plugin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'options-general.php?page=connectors' ) ),
			esc_html__( 'Settings', 'ai-provider-for-ask-sage' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
