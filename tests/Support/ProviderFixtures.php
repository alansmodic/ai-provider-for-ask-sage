<?php
/**
 * Shared fixtures for Ask Sage unit tests.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Support;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication;
use WordPressVIP\AiProviderForAskSage\Models\AskSageNativeTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Models\AskSageOpenAiCompatibleTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Models\AskSageTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Support\Credentials;

/**
 * Factory methods for models, metadata, and messages.
 *
 * @since 1.1.1
 */
class ProviderFixtures {

	/**
	 * Test API key used when wiring authentication.
	 *
	 * @since 1.1.1
	 * @var string
	 */
	public const API_KEY = 'test-key';

	/**
	 * Provider metadata matching the Ask Sage connector.
	 *
	 * @since 1.1.1
	 *
	 * @return ProviderMetadata The metadata.
	 */
	public static function provider_metadata(): ProviderMetadata {
		return new ProviderMetadata(
			Credentials::PROVIDER_ID,
			'Ask Sage',
			ProviderTypeEnum::cloud(),
			'https://chat.asksage.ai/',
			RequestAuthenticationMethod::apiKey()
		);
	}

	/**
	 * Model metadata with the options this provider advertises.
	 *
	 * @since 1.1.1
	 *
	 * @param string $model_id The model ID.
	 * @return ModelMetadata The metadata.
	 */
	public static function model_metadata( string $model_id = 'gpt-4.1-mini' ): ModelMetadata {
		$text_only = array( array( ModalityEnum::text() ) );

		return new ModelMetadata(
			$model_id,
			$model_id . ' (Ask Sage)',
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			array(
				new SupportedOption( OptionEnum::inputModalities(), $text_only ),
				new SupportedOption( OptionEnum::outputModalities(), $text_only ),
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::maxTokens() ),
			)
		);
	}

	/**
	 * A user text message.
	 *
	 * @since 1.1.1
	 *
	 * @param string $text The message text.
	 * @return Message The message.
	 */
	public static function user_message( string $text ): Message {
		return new Message( MessageRoleEnum::user(), array( new MessagePart( $text ) ) );
	}

	/**
	 * A model text message.
	 *
	 * @since 1.1.1
	 *
	 * @param string $text The message text.
	 * @return Message The message.
	 */
	public static function model_message( string $text ): Message {
		return new Message( MessageRoleEnum::model(), array( new MessagePart( $text ) ) );
	}

	/**
	 * A routing model wired to a recording transporter.
	 *
	 * @since 1.1.1
	 *
	 * @param RecordingHttpTransporter|null $transporter Optional transporter.
	 * @return array{0: AskSageTextGenerationModel, 1: RecordingHttpTransporter}
	 */
	public static function routing_model( ?RecordingHttpTransporter $transporter = null ): array {
		$transporter = $transporter ?? new RecordingHttpTransporter();
		$model       = new AskSageTextGenerationModel( self::model_metadata(), self::provider_metadata() );
		self::wire_model( $model, $transporter );

		return array( $model, $transporter );
	}

	/**
	 * A native model wired to a recording transporter.
	 *
	 * @since 1.1.1
	 *
	 * @param RecordingHttpTransporter|null $transporter Optional transporter.
	 * @return array{0: AskSageNativeTextGenerationModel, 1: RecordingHttpTransporter}
	 */
	public static function native_model( ?RecordingHttpTransporter $transporter = null ): array {
		$transporter = $transporter ?? new RecordingHttpTransporter();
		$model       = new AskSageNativeTextGenerationModel( self::model_metadata(), self::provider_metadata() );
		self::wire_model( $model, $transporter );

		return array( $model, $transporter );
	}

	/**
	 * An OpenAI-compatible model wired to a recording transporter.
	 *
	 * @since 1.1.2
	 *
	 * @param RecordingHttpTransporter|null $transporter Optional transporter.
	 * @return array{0: AskSageOpenAiCompatibleTextGenerationModel, 1: RecordingHttpTransporter}
	 */
	public static function openai_model( ?RecordingHttpTransporter $transporter = null ): array {
		$transporter = $transporter ?? new RecordingHttpTransporter();
		$model       = new AskSageOpenAiCompatibleTextGenerationModel( self::model_metadata(), self::provider_metadata() );
		self::wire_model( $model, $transporter );

		return array( $model, $transporter );
	}

	/**
	 * Attaches HTTP and authentication dependencies.
	 *
	 * @since 1.1.1
	 *
	 * @param AskSageTextGenerationModel|AskSageNativeTextGenerationModel|AskSageOpenAiCompatibleTextGenerationModel $model       The model.
	 * @param RecordingHttpTransporter                                    $transporter The transporter.
	 */
	private static function wire_model( $model, RecordingHttpTransporter $transporter ): void {
		$model->setHttpTransporter( $transporter );
		$model->setRequestAuthentication( new AccessTokenAuthentication( self::API_KEY ) );
	}
}
