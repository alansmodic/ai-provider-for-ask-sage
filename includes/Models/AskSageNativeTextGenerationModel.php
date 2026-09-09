<?php
/**
 * Ask Sage native text generation model.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Models;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPressVIP\AiProviderForAskSage\Provider\AskSageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates text using Ask Sage's native /server/query endpoint, which carries the grounding
 * parameters (dataset, persona, live, limit_references) that the OpenAI-compatible surface lacks.
 *
 * @since 1.0.0
 */
class AskSageNativeTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * Ask Sage native options forwarded from ModelConfig custom options.
	 *
	 * @since 1.0.0
	 * @var list<string>
	 */
	private const PASSTHROUGH_OPTIONS = array( 'dataset', 'persona', 'live', 'limit_references' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param Message[] $prompt The conversation turns.
	 * @return GenerativeAiResult The generation result.
	 *
	 * @throws ResponseException If the response is unsuccessful or missing required data.
	 */
	public function generateTextResult( array $prompt ): GenerativeAiResult {
		$request = new Request(
			HttpMethodEnum::POST(),
			AskSageProvider::url( 'server/query' ),
			array( 'Content-Type' => 'application/json' ),
			$this->prepare_query_params( $prompt ),
			$this->getRequestOptions()
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		$data = $response->getData();
		if ( ! is_array( $data ) || ! isset( $data['message'] ) ) {
			throw ResponseException::fromMissingData( 'Ask Sage', 'message' );
		}

		return $this->parse_response_to_result( $data );
	}

	/**
	 * Builds the /server/query request body from the prompt and model configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param Message[] $prompt The conversation turns.
	 * @return array<string, mixed> The request body.
	 */
	private function prepare_query_params( array $prompt ): array {
		$config = $this->getConfig();

		list( $system_from_messages, $user_message ) = $this->flatten_prompt( $prompt );

		$params = array(
			'message' => $user_message,
			'model'   => $this->metadata()->getId(),
			// Ask Sage omits token counts unless explicitly requested.
			'usage'   => true,
		);

		$system = (string) ( $config->getSystemInstruction() ?? '' );
		if ( '' !== $system_from_messages ) {
			$system = '' === $system ? $system_from_messages : $system . "\n\n" . $system_from_messages;
		}
		if ( '' !== $system ) {
			$params['system_prompt'] = $system;
		}

		if ( null !== $config->getTemperature() ) {
			$params['temperature'] = (float) $config->getTemperature();
		}

		// Dataset grounding, personas and live-retrieval control travel through custom options.
		$custom_options = $config->getCustomOptions();
		foreach ( self::PASSTHROUGH_OPTIONS as $option ) {
			if ( array_key_exists( $option, $custom_options ) ) {
				$params[ $option ] = $custom_options[ $option ];
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the Ask Sage /server/query request body.
			 *
			 * Useful for enforcing a mandated grounding dataset across an entire site.
			 * Returned values are sanitized before they are sent.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $params   The request body.
			 * @param string               $model_id The selected model ID.
			 */
			$filtered = apply_filters( 'ai_provider_for_ask_sage_query_params', $params, $this->metadata()->getId() );
			if ( is_array( $filtered ) ) {
				$params = $filtered;
			}
		}

		return $this->sanitize_query_params( $params );
	}

	/**
	 * Strips non-JSON-safe values from a /server/query body.
	 *
	 * Filter callbacks must not be able to inject objects or resources into the
	 * payload that is sent to the remote API.
	 *
	 * @since 1.1.1
	 *
	 * @param array<string|int, mixed> $params The request body.
	 * @param int                      $depth  Current recursion depth.
	 * @return array<string|int, mixed> The sanitized request body.
	 */
	private function sanitize_query_params( array $params, int $depth = 0 ): array {
		if ( $depth > 5 ) {
			return array();
		}

		$sanitized = array();

		foreach ( $params as $key => $value ) {
			if ( is_string( $key ) && '' === $key ) {
				continue;
			}

			if ( is_object( $value ) || is_resource( $value ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_query_params( $value, $depth + 1 );
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Reduces the prompt to a system instruction and a single user message.
	 *
	 * Ask Sage's /server/query accepts one message plus an optional system prompt rather than
	 * a role array, so earlier turns are folded into the message as inline context.
	 *
	 * @since 1.0.0
	 *
	 * @param Message[] $prompt The conversation turns.
	 * @return array{0: string, 1: string} The system text and user text.
	 */
	private function flatten_prompt( array $prompt ): array {
		$system = '';
		$turns  = array();

		foreach ( $prompt as $message ) {
			if ( ! $message instanceof Message ) {
				continue;
			}

			$role = $message->getRole()->value;
			$text = $this->extract_message_text( $message );

			if ( '' === $text ) {
				continue;
			}

			if ( 'system' === $role ) {
				$system = '' === $system ? $text : $system . "\n\n" . $text;
				continue;
			}

			$turns[] = strtoupper( $role ) . ': ' . $text;
		}

		$user = (string) array_pop( $turns );
		$user = (string) preg_replace( '/^USER:\s*/', '', $user );

		if ( ! empty( $turns ) ) {
			$user = "Conversation so far:\n" . implode( "\n", $turns ) . "\n\nCurrent question:\n" . $user;
		}

		return array( $system, $user );
	}

	/**
	 * Concatenates the text parts of a message.
	 *
	 * @since 1.0.0
	 *
	 * @param Message $message The message.
	 * @return string The combined text.
	 */
	private function extract_message_text( Message $message ): string {
		$text = '';

		foreach ( $message->getParts() as $part ) {
			if ( ! $part instanceof MessagePart ) {
				continue;
			}

			$part_text = $part->getText();
			if ( null !== $part_text ) {
				$text .= $part_text;
			}
		}

		return $text;
	}

	/**
	 * Converts an Ask Sage response into a GenerativeAiResult.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data The decoded response.
	 * @return GenerativeAiResult The result.
	 */
	private function parse_response_to_result( array $data ): GenerativeAiResult {
		$message   = new Message(
			MessageRoleEnum::model(),
			array( new MessagePart( (string) $data['message'] ) )
		);
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );

		$token_usage = $this->parse_token_usage( $data );

		$id = isset( $data['uuid'] ) ? (string) $data['uuid'] : wp_generate_uuid4();

		// Preserve grounding citations for consumers that surface them.
		$additional_data = array();
		if ( isset( $data['references'] ) ) {
			$additional_data['references'] = $data['references'];
		}

		return new GenerativeAiResult(
			$id,
			array( $candidate ),
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Extracts token usage from an Ask Sage response.
	 *
	 * Requested via the `usage` request parameter. The exact response shape is not documented,
	 * so several known spellings are accepted and zero counts are reported when absent rather
	 * than failing the request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data The decoded response.
	 * @return TokenUsage The token usage.
	 */
	private function parse_token_usage( array $data ): TokenUsage {
		$usage = array();
		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$usage = $data['usage'];
		} elseif ( isset( $data['tokens'] ) && is_array( $data['tokens'] ) ) {
			$usage = $data['tokens'];
		} else {
			$usage = $data;
		}

		$prompt     = $this->first_int( $usage, array( 'prompt_tokens', 'input_tokens', 'promptTokens' ) );
		$completion = $this->first_int( $usage, array( 'completion_tokens', 'output_tokens', 'completionTokens' ) );
		$total      = $this->first_int( $usage, array( 'total_tokens', 'tokens', 'totalTokens' ) );

		if ( 0 === $total ) {
			$total = $prompt + $completion;
		}

		return new TokenUsage( $prompt, $completion, $total );
	}

	/**
	 * Returns the first integer value found among the given keys.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $source The source array.
	 * @param string[]             $keys   Candidate keys, in priority order.
	 * @return int The value, or 0 when none is present.
	 */
	private function first_int( array $source, array $keys ): int {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && is_numeric( $source[ $key ] ) ) {
				return (int) $source[ $key ];
			}
		}

		return 0;
	}
}
