<?php
/**
 * Tests for Ask Sage model discovery.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Metadata;

use RuntimeException;
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
		$this->assertSame( 'claude-sonnet (Ask Sage)', $directory->getModelMetadata( 'claude-sonnet' )->getName() );
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
