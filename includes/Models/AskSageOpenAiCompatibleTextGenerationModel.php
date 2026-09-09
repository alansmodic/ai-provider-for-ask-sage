<?php
/**
 * Ask Sage OpenAI-compatible text generation model.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Support\Credentials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates text using Ask Sage's OpenAI-compatible surface.
 *
 * Ask Sage exposes a drop-in OpenAI API at `/server/openai/v1/`. It follows the OpenAI
 * specification, which means the SDK's OpenAI-compatible base class handles multi-turn
 * conversations, sampling parameters, tool calling and token usage without further work.
 *
 * Authentication is applied after this method returns, by AccessTokenAuthentication, which
 * sends `Authorization: Bearer` for this surface. Grounding parameters are not available
 * here; those requests are routed to the native endpoint instead.
 *
 * @since 1.1.0
 */
class AskSageOpenAiCompatibleTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Path segment for Ask Sage's OpenAI-compatible API, relative to the instance base URL.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private const API_PATH = '/server/openai/v1/';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param HttpMethodEnum                     $method  The HTTP method.
	 * @param string                             $path    The API endpoint path, relative to the base URI.
	 * @param array<string, string|list<string>> $headers The request headers.
	 * @param string|array<string, mixed>|null   $data    The request data.
	 * @return Request The request object.
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		return new Request(
			$method,
			Credentials::base_url() . self::API_PATH . ltrim( $path, '/' ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
