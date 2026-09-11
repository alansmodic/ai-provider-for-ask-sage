<?php
/**
 * Tests for Ask Sage model discovery.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Metadata;

use RuntimeException;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\DTO\RequiredOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication;
use WordPressVIP\AiProviderForAskSage\Metadata\AskSageModelMetadataDirectory;
use WordPressVIP\AiProviderForAskSage\Tests\Support\ProviderFixtures;
use WordPressVIP\AiProviderForAskSage\Tests\Support\RecordingHttpTransporter;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Metadata\AskSageModelMetadataDirectory
 */
class AskSageModelMetadataDirectoryTest extends TestCase {

	public function test_normalizes_string_and_object_model_ids(): void {
		$directory = $this->wired_directory( new RecordingHttpTransporter() );

		$this->assertTrue( $directory->hasModelMetadata( 'gpt-4.1-mini' ) );
		$this->assertTrue( $directory->hasModelMetadata( 'claude-sonnet' ) );
		$this->assertSame( 'claude-sonnet', $directory->getModelMetadata( 'claude-sonnet' )->getId() );
		$this->assertStringContainsString( 'Ask Sage', $directory->getModelMetadata( 'claude-sonnet' )->getName() );
	}

	public function test_falls_back_when_the_request_fails(): void {
		$transporter            = new RecordingHttpTransporter();
		$transporter->exception = new RuntimeException( 'network down' );
		$directory              = $this->wired_directory( $transporter );

		$this->assertTrue( $directory->hasModelMetadata( 'gpt-4.1-mini' ) );
		$this->assertFalse( $directory->hasModelMetadata( 'claude-sonnet' ) );
	}

	public function test_falls_back_when_payload_has_no_usable_ids(): void {
		$transporter           = new RecordingHttpTransporter();
		$transporter->response = RecordingHttpTransporter::json_response(
			array(
				'response' => array(
					array( 'label' => 'not-an-id' ),
					'',
				),
			)
		);
		$directory             = $this->wired_directory( $transporter );

		$this->assertTrue( $directory->hasModelMetadata( 'gpt-4.1-mini' ) );
		$this->assertCount( 1, $directory->listModelMetadata() );
	}

	/**
	 * Editorial Notes and other abilities that request structured JSON output (via
	 * as_json_response()) require the model to advertise OptionEnum::outputSchema(), or
	 * ModelRequirements::areMetBy() rejects Ask Sage entirely with "no connected provider
	 * supports text generation" before a request is ever sent. The OpenAI-compatible surface
	 * already forwards outputSchema as response_format, so this is only a metadata gap.
	 */
	public function test_advertises_output_schema_support_for_structured_output_abilities(): void {
		$directory = $this->wired_directory( new RecordingHttpTransporter() );

		$options = $directory->getModelMetadata( 'gpt-4.1-mini' )->getSupportedOptions();

		$names = array_map(
			static function ( $option ) {
				return $option->getName()->value;
			},
			$options
		);

		$this->assertContains( OptionEnum::outputSchema()->value, $names );
	}

	/**
	 * as_json_response() sets BOTH outputSchema and outputMimeType ('application/json') on
	 * ModelConfig; the SDK only forwards outputSchema as response_format when outputMimeType is
	 * exactly 'application/json'. Advertising outputSchema alone still left
	 * ModelRequirements::areMetBy() failing on the unmet outputMimeType requirement, so this
	 * checks the combined requirement the same way as_json_response() actually assembles it,
	 * not just each option in isolation.
	 */
	public function test_meets_combined_structured_output_requirements_from_as_json_response(): void {
		$directory = $this->wired_directory( new RecordingHttpTransporter() );
		$metadata  = $directory->getModelMetadata( 'gpt-4.1-mini' );

		$requirements = new ModelRequirements(
			array( CapabilityEnum::textGeneration() ),
			array(
				new RequiredOption( OptionEnum::outputSchema(), array( 'type' => 'object' ) ),
				new RequiredOption( OptionEnum::outputMimeType(), 'application/json' ),
			)
		);

		$this->assertTrue( $requirements->areMetBy( $metadata ) );
	}

	/**
	 * Wires a directory to a recording transporter.
	 *
	 * @param RecordingHttpTransporter $transporter The transporter.
	 * @return AskSageModelMetadataDirectory The directory.
	 */
	private function wired_directory( RecordingHttpTransporter $transporter ): AskSageModelMetadataDirectory {
		$directory = new AskSageModelMetadataDirectory();
		$directory->setHttpTransporter( $transporter );
		$directory->setRequestAuthentication( new AccessTokenAuthentication( ProviderFixtures::API_KEY ) );

		return $directory;
	}
}
