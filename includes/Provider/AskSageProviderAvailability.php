<?php
/**
 * Ask Sage provider availability.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPressVIP\AiProviderForAskSage\Support\Credentials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports whether the Ask Sage provider is configured.
 *
 * Unlike providers that can be probed anonymously, every Ask Sage endpoint requires a key,
 * so a key check is the meaningful signal here. A network probe is deliberately avoided to
 * keep admin page loads fast and to avoid egress from sites behind restrictive boundaries.
 *
 * @since 1.0.0
 */
class AskSageProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return '' !== Credentials::api_key();
	}
}
