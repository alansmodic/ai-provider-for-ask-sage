<?php
/**
 * Tests for the native /server/query model.
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
 * @covers \WordPressVIP\AiProviderForAskSage\Models\AskSageNativeTextGenerationModel
 */
class AskSageNativeTextGenerationModelTest extends TestCase {

	public function test_flattens_multi_turn_prompt_into_one_message(): void {
		list( $model, $transporter ) = ProviderFixtures::native_model();

		$model->generateTextResult(
			array(
				ProviderFixtures::user_message( 'Hello' ),
				ProviderFixtures::model_message( 'Hi there' ),
				ProviderFixtures::user_message( 'Is FedRAMP High enough?' ),
			)
		);

		$body = $transporter->last_request->getData();
		$this->assertIsArray( $body );
		$this->assertStringContainsString( "Conversation so far:\n", $body['message'] );
		$this->assertStringContainsString( 'USER: Hello', $body['message'] );
		$this->assertStringContainsString( 'MODEL: Hi there', $body['message'] );
		$this->assertStringContainsString( "Current question:\nIs FedRAMP High enough?", $body['message'] );
	}

	public function test_system_instruction_and_sampling_are_forwarded(): void {
		list( $model, $transporter ) = ProviderFixtures::native_model();
		$model->getConfig()->setSystemInstruction( 'You are a policy assistant.' );
		$model->getConfig()->setTemperature( 0.2 );
		$model->getConfig()->setCustomOptions(
			array(
				'dataset'          => array( 'agency_policy_docs' ),
				'persona'          => 7,
				'live'             => 0,
				'limit_references' => 3,
			)
		);

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Cite the handbook.' ) ) );

		$body = $transporter->last_request->getData();
		$this->assertIsArray( $body );
		$this->assertSame( 'You are a policy assistant.', $body['system_prompt'] );
		$this->assertSame( 0.2, $body['temperature'] );
		$this->assertSame( array( 'agency_policy_docs' ), $body['dataset'] );
		$this->assertSame( 7, $body['persona'] );
		$this->assertSame( 0, $body['live'] );
		$this->assertSame( 3, $body['limit_references'] );
		$this->assertTrue( $body['usage'] );
		$this->assertSame( 'gpt-4.1-mini', $body['model'] );
	}

	public function test_filter_cannot_inject_objects_into_query_body(): void {
		add_filter(
			'ai_provider_for_ask_sage_query_params',
			static function ( array $params ) {
				$params['evil']    = new \stdClass();
				$params['dataset'] = array( 'agency_policy_docs' );
				return $params;
			}
		);

		list( $model, $transporter ) = ProviderFixtures::native_model();
		$model->getConfig()->setCustomOptions( array( 'dataset' => array( 'x' ) ) );
		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$body = $transporter->last_request->getData();
		$this->assertIsArray( $body );
		$this->assertArrayNotHasKey( 'evil', $body );
		$this->assertSame( array( 'agency_policy_docs' ), $body['dataset'] );
	}

	public function test_parses_usage_and_references(): void {
		list( $model ) = ProviderFixtures::native_model();

		$result = $model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$this->assertSame( 'Grounded answer.', $result->toText() );
		$this->assertSame( array( 'doc-1' ), $result->getAdditionalData()['references'] );
		$this->assertSame( 10, $result->getTokenUsage()->getPromptTokens() );
		$this->assertSame( 4, $result->getTokenUsage()->getCompletionTokens() );
		$this->assertSame( 14, $result->getTokenUsage()->getTotalTokens() );
	}

	public function test_missing_message_throws_response_exception(): void {
		$transporter           = new RecordingHttpTransporter();
		$transporter->response = RecordingHttpTransporter::json_response( array( 'ok' => true ) );
		list( $model )         = ProviderFixtures::native_model( $transporter );

		$this->expectException( ResponseException::class );
		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );
	}

	public function test_error_status_in_200_response_throws_response_exception_with_ask_sage_message(): void {
		$transporter           = new RecordingHttpTransporter();
		$transporter->response = RecordingHttpTransporter::json_response(
			array(
				'response' => 'Token is invalid [2]',
				'status'   => 400,
			)
		);
		list( $model ) = ProviderFixtures::native_model( $transporter );

		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( 'Ask Sage API error (400): Token is invalid [2]' );
		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );
	}
}
