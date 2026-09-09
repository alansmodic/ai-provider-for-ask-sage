<?php
/**
 * Ask Sage request authentication.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Auth;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticates requests using Ask Sage's x-access-tokens header.
 *
 * Ask Sage does not use the standard "Authorization: Bearer" scheme. The provider registry
 * validates the authentication instance against the class returned by
 * RequestAuthenticationMethod::getImplementationClass() using an instanceof check, so extending
 * ApiKeyRequestAuthentication and overriding only the header behaviour keeps the provider
 * compatible with the registry while sending the header Ask Sage expects.
 *
 * Ask Sage accepts its static API key directly as an access token, so no exchange against
 * /user/get-token-with-api-key is required.
 *
 * @since 1.0.0
 */
class AccessTokenAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function authenticateRequest( Request $request ): Request {
		return $request->withHeader( 'x-access-tokens', $this->getApiKey() );
	}
}
