<?php
/**
 * Ask Sage model metadata directory.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Metadata;

use Throwable;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPressVIP\AiProviderForAskSage\Provider\AskSageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers the models exposed by the tenant's Ask Sage instance.
 *
 * Extends the SDK's caching directory base, which handles listModelMetadata(),
 * hasModelMetadata() and getModelMetadata() plus result caching.
 *
 * @since 1.0.0
 */
class AskSageModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Fallback model used when the tenant's model list cannot be retrieved.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const FALLBACK_MODEL = 'gpt-4.1-mini';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, ModelMetadata> Map of model ID to metadata.
	 */
	protected function sendListModelsRequest(): array {
		$model_ids = $this->fetch_model_ids();

		$models_map = array();
		foreach ( $model_ids as $model_id ) {
			$models_map[ $model_id ] = $this->build_model_metadata( $model_id );
		}

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Retrieves the available model IDs from the Ask Sage instance.
	 *
	 * Falls back to a single known model when the endpoint is unavailable so that
	 * provider registration never hard-fails on a transient network error.
	 *
	 * @since 1.0.0
	 *
	 * @return list<string> The model IDs.
	 */
	private function fetch_model_ids(): array {
		try {
			$request  = new Request(
				HttpMethodEnum::POST(),
				AskSageProvider::url( 'server/get-models' ),
				array( 'Content-Type' => 'application/json' ),
				array()
			);
			$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
			$response = $this->getHttpTransporter()->send( $request );

			ResponseUtil::throwIfNotSuccessful( $response );

			$data = $response->getData();

			$models = array();
			if ( is_array( $data ) ) {
				if ( isset( $data['response'] ) && is_array( $data['response'] ) ) {
					$models = $data['response'];
				} elseif ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
					$models = $data['models'];
				}
			}

			$models = $this->normalize_model_ids( $models );

			if ( ! empty( $models ) ) {
				return $models;
			}
		} catch ( Throwable $e ) {
			if ( function_exists( 'wp_trigger_error' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_trigger_error(
					__METHOD__,
					sprintf(
						/* translators: %s: Error message from the Ask Sage API or HTTP layer. */
						__( 'Ask Sage model discovery failed; using the fallback model. %s', 'ai-provider-for-ask-sage' ),
						$e->getMessage()
					)
				);
			}
		}

		return array( self::FALLBACK_MODEL );
	}

	/**
	 * Extracts string model IDs from a get-models payload.
	 *
	 * @since 1.1.1
	 *
	 * @param array<mixed> $models Raw model list from the API.
	 * @return string[] The model IDs.
	 */
	private function normalize_model_ids( array $models ): array {
		$ids = array();

		foreach ( $models as $model ) {
			$model_id = '';

			if ( is_string( $model ) ) {
				$model_id = $model;
			} elseif ( is_array( $model ) ) {
				foreach ( array( 'id', 'model', 'name' ) as $key ) {
					if ( isset( $model[ $key ] ) && is_string( $model[ $key ] ) ) {
						$model_id = $model[ $key ];
						break;
					}
				}
			}

			$model_id = trim( $model_id );
			if ( '' === $model_id ) {
				continue;
			}

			$ids[] = $model_id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Builds metadata for a single Ask Sage model.
	 *
	 * The supported options list is significant: ModelRequirements::areMetBy() rejects any model
	 * that does not advertise every option set on the caller's ModelConfig, so an under-advertised
	 * model is invisible to automatic model selection.
	 *
	 * The advertised set is the union of both Ask Sage surfaces. AskSageTextGenerationModel routes
	 * each request to whichever surface can serve it: the native /server/query endpoint when
	 * grounding is requested, and the OpenAI-compatible endpoint otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model ID.
	 * @return ModelMetadata The model metadata.
	 */
	private function build_model_metadata( string $model_id ): ModelMetadata {
		$text_only = array( array( ModalityEnum::text() ) );

		return new ModelMetadata(
			$model_id,
			ucfirst( str_replace( array( '-', '_' ), ' ', $model_id ) ) . ' (Ask Sage)',
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			array(
				// Honored by both surfaces.
				new SupportedOption( OptionEnum::inputModalities(), $text_only ),
				new SupportedOption( OptionEnum::outputModalities(), $text_only ),
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::customOptions() ),
				// Honored by the OpenAI-compatible surface.
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::outputSchema() ),
				// as_json_response() requires outputMimeType alongside outputSchema; the SDK only
				// builds response_format when outputMimeType is exactly 'application/json'.
				new SupportedOption( OptionEnum::outputMimeType(), array( 'application/json' ) ),
			)
		);
	}
}
