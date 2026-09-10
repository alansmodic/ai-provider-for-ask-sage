<?php
/**
 * Validation for Ask Sage's non-standard error responses.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.1.2
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Support;

use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ask Sage reports request failures (invalid token, bad model, etc.) with an HTTP 200 status
 * and the real outcome embedded in the JSON body as `status` and `response`, on both the native
 * and OpenAI-compatible surfaces. Neither WordPress\AiClient\Providers\Http\Util\ResponseUtil nor
 * the OpenAI-compatible base class know about this convention, since they rely on the HTTP status
 * code alone, so an Ask Sage-side failure was previously misreported as a missing "choices" or
 * "message" key instead of the actual error Ask Sage returned.
 *
 * @since 1.1.2
 */
class AskSageResponseValidator {

	/**
	 * Throws when the response body indicates Ask Sage reported an error despite the HTTP
	 * status suggesting success.
	 *
	 * @since 1.1.2
	 *
	 * @param Response $response The HTTP response to inspect.
	 * @throws ResponseException When Ask Sage's embedded status indicates a failure.
	 */
	public static function throwIfErrorStatus( Response $response ): void {
		$data = $response->getData();

		if ( ! is_array( $data ) || ! isset( $data['status'] ) || ! is_numeric( $data['status'] ) ) {
			return;
		}

		$status = (int) $data['status'];
		if ( $status < 400 ) {
			return;
		}

		$message = isset( $data['response'] ) && is_string( $data['response'] )
			? $data['response']
			: 'Ask Sage reported an error without a message.';

		throw new ResponseException( sprintf( 'Ask Sage API error (%d): %s', $status, $message ) );
	}
}
