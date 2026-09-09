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
 * The OpenAI-compatible surface is the exception: it expects `Authorization: Bearer`. The SDK
 * always calls authenticateRequest() after createRequest(), so header selection happens here
 * based on the request URI. That avoids sending both headers on the same request.
 *
 * @since 1.0.0
 */
class AccessTokenAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * Path fragment that identifies Ask Sage's OpenAI-compatible surface.
	 *
	 * @since 1.1.1
	 * @var string
	 */
	private const OPENAI_PATH_FRAGMENT = '/server/openai/';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The request to authenticate.
	 * @return Request The authenticated request.
	 */
	public function authenticateRequest( Request $request ): Request {
		$api_key = $this->getApiKey();
		if ( '' === $api_key ) {
			return $request;
		}

		if ( false !== strpos( $request->getUri(), self::OPENAI_PATH_FRAGMENT ) ) {
			return $request->withHeader( 'Authorization', 'Bearer ' . $api_key );
		}

		return $request->withHeader( 'x-access-tokens', $api_key );
	}
}
