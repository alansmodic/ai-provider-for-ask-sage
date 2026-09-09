<?php
/**
 * Ask Sage provider.
 *
 * @package WordPressVIP\AiProviderForAskSage
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPressVIP\AiProviderForAskSage\Metadata\AskSageModelMetadataDirectory;
use WordPressVIP\AiProviderForAskSage\Models\AskSageTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Support\Credentials;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for the Ask Sage provider.
 *
 * @since 1.0.0
 */
class AskSageProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return Credentials::base_url();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			Credentials::PROVIDER_ID,
			'Ask Sage',
			ProviderTypeEnum::cloud(),
			'https://chat.asksage.ai/',
			RequestAuthenticationMethod::apiKey(),
		);

		// Provider description support was added in AI Client 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$provider_meta[] = function_exists( '__' )
				? __( 'FedRAMP High / IL-authorized generative AI, with dataset grounding and personas.', 'ai-provider-for-ask-sage' )
				: 'FedRAMP High / IL-authorized generative AI, with dataset grounding and personas.';
		}

		// Provider logo path support was added in AI Client 1.3.0.
		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$provider_meta[] = defined( 'AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR' )
				? AI_PROVIDER_FOR_ASK_SAGE_PLUGIN_DIR . 'includes/Provider/logo.png'
				: dirname( __DIR__, 2 ) . '/includes/Provider/logo.png';
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new AskSageProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new AskSageModelMetadataDirectory();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new AskSageTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported Ask Sage model capabilities for model: ' . $model_metadata->getId()
		);
	}
}
