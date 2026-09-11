<?php
/**
 * Ask Sage text generation model.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Models;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes text generation to the appropriate Ask Sage surface.
 *
 * Ask Sage exposes two surfaces with complementary strengths:
 *
 * - `/server/query` (native) carries the grounding parameters — dataset, persona, live and
 *   limit_references — but supports only a small set of sampling options.
 * - `/server/openai/v1/chat/completions` follows the OpenAI specification, adding max tokens,
 *   nucleus sampling, penalties, tool calling, native multi-turn messages and token usage, but
 *   without the grounding parameters.
 *
 * Requests that ask for grounding go to the native surface; everything else goes to the
 * OpenAI-compatible surface. This keeps the provider selectable for the full range of options an
 * AI-aware plugin might set, without giving up grounding when it is requested.
 *
 * Routing can be forced with the `asksage.endpoint` custom option, set to `native` or `openai`.
 *
 * @since 1.0.0
 */
class AskSageTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * Custom options that only the native surface understands.
	 *
	 * @since 1.1.0
	 * @var list<string>
	 */
	public const GROUNDING_OPTIONS = array( 'dataset', 'persona', 'live', 'limit_references' );

	/**
	 * Custom option used to force a specific surface.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const ENDPOINT_OPTION = 'asksage.endpoint';

	/**
	 * Options that only the OpenAI-compatible surface honors.
	 *
	 * Includes sampling, tool calling, and structured JSON output. Grounded requests
	 * still use `/server/query`, so these are dropped with a WP_DEBUG notice rather
	 * than silently ignored.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const OPENAI_ONLY_CONFIG = array(
		'getMaxTokens'            => 'maxTokens',
		'getTopP'                 => 'topP',
		'getFrequencyPenalty'     => 'frequencyPenalty',
		'getPresencePenalty'      => 'presencePenalty',
		'getFunctionDeclarations' => 'functionDeclarations',
		'getOutputSchema'         => 'outputSchema',
		'getOutputMimeType'       => 'outputMimeType',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Messages\DTO\Message[] $prompt The conversation turns.
	 * @return GenerativeAiResult The generation result.
	 */
	public function generateTextResult( array $prompt ): GenerativeAiResult {
		$delegate = $this->resolve_delegate();

		return $delegate->generateTextResult( $prompt );
	}

	/**
	 * Builds the delegate model for this request and hands it this model's dependencies.
	 *
	 * @since 1.1.0
	 *
	 * @return TextGenerationModelInterface&ModelInterface The delegate model.
	 */
	private function resolve_delegate(): TextGenerationModelInterface {
		$use_native = $this->should_use_native();

		$delegate = $use_native
			? new AskSageNativeTextGenerationModel( $this->metadata(), $this->providerMetadata() )
			: new AskSageOpenAiCompatibleTextGenerationModel( $this->metadata(), $this->providerMetadata() );

		$delegate->setHttpTransporter( $this->getHttpTransporter() );
		$delegate->setRequestAuthentication( $this->getRequestAuthentication() );
		$delegate->setConfig( $this->getConfig() );

		$request_options = $this->getRequestOptions();
		if ( null !== $request_options ) {
			$delegate->setRequestOptions( $request_options );
		}

		if ( $use_native ) {
			$this->warn_about_dropped_options();
		}

		return $delegate;
	}

	/**
	 * Determines whether this request needs the native grounding surface.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True when the native surface should be used.
	 */
	private function should_use_native(): bool {
		$custom_options = $this->getConfig()->getCustomOptions();

		if ( isset( $custom_options[ self::ENDPOINT_OPTION ] ) ) {
			return 'native' === $custom_options[ self::ENDPOINT_OPTION ];
		}

		foreach ( self::GROUNDING_OPTIONS as $option ) {
			if ( array_key_exists( $option, $custom_options ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Warns when a grounded request also sets options only the OpenAI surface honors.
	 *
	 * Grounding wins, so those options are not applied. Surfacing it keeps the behaviour
	 * discoverable rather than silent.
	 *
	 * @since 1.1.0
	 */
	private function warn_about_dropped_options(): void {
		if ( ! function_exists( 'wp_trigger_error' ) || ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		$config  = $this->getConfig();
		$dropped = array();

		foreach ( self::OPENAI_ONLY_CONFIG as $getter => $label ) {
			if ( method_exists( $config, $getter ) && null !== $config->{$getter}() ) {
				$dropped[] = $label;
			}
		}

		if ( empty( $dropped ) ) {
			return;
		}

		wp_trigger_error(
			__METHOD__,
			sprintf(
				/* translators: %s: Comma-separated list of configuration option names. */
				__( 'Ask Sage grounding options were requested, so the native endpoint was used and these options were not applied: %s.', 'ai-provider-for-ask-sage' ),
				implode( ', ', $dropped )
			)
		);
	}
}
