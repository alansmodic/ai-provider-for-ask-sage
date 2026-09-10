<?php
/**
 * Tests for the OpenAI-compatible /server/openai/v1/ model.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Models;

use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPressVIP\AiProviderForAskSage\Tests\Support\ProviderFixtures;
use WordPressVIP\AiProviderForAskSage\Tests\Support\RecordingHttpTransporter;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Models\AskSageOpenAiCompatibleTextGenerationModel
 */
class AskSageOpenAiCompatibleTextGenerationModelTest extends TestCase {

	public function test_parses_a_normal_openai_shaped_response(): void {
		list( $model ) = ProviderFixtures::openai_model();

		$result = $model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$this->assertSame( 'OpenAI answer.', $result->toText() );
	}

	/**
	 * Abilities like Editorial Notes call as_json_response(), which sets ModelConfig's
	 * outputSchema. The base OpenAI-compatible model translates that into response_format;
	 * this confirms Ask Sage's surface actually receives it, now that the model metadata
	 * advertises the option (see AskSageModelMetadataDirectoryTest).
	 */
	public function test_output_schema_is_forwarded_as_response_format(): void {
		list( $model, $transporter ) = ProviderFixtures::openai_model();
		$schema                      = array(
			'type'       => 'object',
			'properties' => array( 'suggestions' => array( 'type' => 'array' ) ),
		);
		$model->getConfig()->setOutputSchema( $schema );

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$body = $transporter->last_request->getData();
		$this->assertIsArray( $body );
		$this->assertSame( 'json_schema', $body['response_format']['type'] );
		$this->assertSame( $schema, $body['response_format']['json_schema'] );
	}

	/**
	 * Ask Sage reports failures on this surface (invalid token, unknown model, etc.) with an
	 * HTTP 200 status and the real outcome embedded in the response body, rather than a 4xx/5xx
	 * status. Left unchecked, this surfaces as a confusing "missing choices key" exception
	 * instead of the actual error Ask Sage returned.
	 */
	public function test_error_status_in_200_response_throws_response_exception_with_ask_sage_message(): void {
		$transporter           = new RecordingHttpTransporter();
		$transporter->response = RecordingHttpTransporter::json_response(
			array(
				'response' => 'Token is invalid [2]',
				'status'   => 400,
			)
		);
		list( $model ) = ProviderFixtures::openai_model( $transporter );

		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( 'Ask Sage API error (400): Token is invalid [2]' );
		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );
	}
}
