<?php
/**
 * Recording HTTP transporter for unit tests.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Support;

use Throwable;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Captures outbound requests and returns canned JSON responses.
 *
 * @since 1.1.1
 */
class RecordingHttpTransporter implements HttpTransporterInterface {

	/**
	 * Most recent request, if any.
	 *
	 * @var Request|null
	 */
	public $last_request = null;

	/**
	 * All requests in send order.
	 *
	 * @var Request[]
	 */
	public $requests = array();

	/**
	 * Optional exception thrown instead of returning a response.
	 *
	 * @var Throwable|null
	 */
	public $exception = null;

	/**
	 * Optional explicit response. When null, a URI-based fixture is used.
	 *
	 * @var Response|null
	 */
	public $response = null;

	/**
	 * {@inheritDoc}
	 */
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		unset( $options );

		$this->last_request = $request;
		$this->requests[]   = $request;

		if ( $this->exception instanceof Throwable ) {
			throw $this->exception;
		}

		if ( $this->response instanceof Response ) {
			return $this->response;
		}

		$uri = $request->getUri();

		if ( false !== strpos( $uri, '/server/get-models' ) ) {
			return self::json_response(
				array(
					'response' => array(
						'gpt-4.1-mini',
						array( 'id' => 'claude-sonnet' ),
					),
				)
			);
		}

		if ( false !== strpos( $uri, '/server/query' ) ) {
			return self::json_response(
				array(
					'message'    => 'Grounded answer.',
					'uuid'       => 'native-1',
					'usage'      => array(
						'prompt_tokens'     => 10,
						'completion_tokens' => 4,
						'total_tokens'      => 14,
					),
					'references' => array( 'doc-1' ),
				)
			);
		}

		return self::json_response(
			array(
				'id'      => 'openai-1',
				'choices' => array(
					array(
						'message'       => array(
							'role'    => 'assistant',
							'content' => 'OpenAI answer.',
						),
						'finish_reason' => 'stop',
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 3,
					'completion_tokens' => 2,
					'total_tokens'      => 5,
				),
			)
		);
	}

	/**
	 * Builds a JSON HTTP response.
	 *
	 * @since 1.1.1
	 *
	 * @param array<string, mixed> $data   Response body.
	 * @param int                  $status HTTP status code.
	 * @return Response The response.
	 */
	public static function json_response( array $data, int $status = 200 ): Response {
		return new Response(
			$status,
			array( 'Content-Type' => 'application/json' ),
			(string) json_encode( $data )
		);
	}
}
