<?php
/**
 * Tests for per-request surface routing.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Models;

use WordPressVIP\AiProviderForAskSage\Models\AskSageTextGenerationModel;
use WordPressVIP\AiProviderForAskSage\Tests\Support\ProviderFixtures;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Models\AskSageTextGenerationModel
 * @covers \WordPressVIP\AiProviderForAskSage\Models\AskSageNativeTextGenerationModel
 * @covers \WordPressVIP\AiProviderForAskSage\Models\AskSageOpenAiCompatibleTextGenerationModel
 * @covers \WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication
 */
class AskSageTextGenerationModelTest extends TestCase {

	public function test_dataset_option_routes_to_native_query(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions( array( 'dataset' => array( 'agency_policy_docs' ) ) );

		$result = $model->generateTextResult( array( ProviderFixtures::user_message( 'Is FedRAMP High enough?' ) ) );

		$this->assertNotNull( $transporter->last_request );
		$this->assertStringContainsString( '/server/query', $transporter->last_request->getUri() );
		$this->assertSame( array( 'agency_policy_docs' ), $transporter->last_request->getData()['dataset'] );
		$this->assertSame( 'Grounded answer.', $result->toText() );
		$this->assertSame( ProviderFixtures::API_KEY, $transporter->last_request->getHeaderAsString( 'x-access-tokens' ) );
		$this->assertNull( $transporter->last_request->getHeader( 'Authorization' ) );
	}

	public function test_ungrounded_request_routes_to_openai_compatible_endpoint(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();

		$result = $model->generateTextResult( array( ProviderFixtures::user_message( 'Summarize this.' ) ) );

		$this->assertNotNull( $transporter->last_request );
		$this->assertStringContainsString( '/server/openai/v1/chat/completions', $transporter->last_request->getUri() );
		$this->assertSame( 'OpenAI answer.', $result->toText() );
		$this->assertSame( 'Bearer ' . ProviderFixtures::API_KEY, $transporter->last_request->getHeaderAsString( 'Authorization' ) );
		$this->assertNull( $transporter->last_request->getHeader( 'x-access-tokens' ) );
	}

	public function test_live_zero_still_routes_to_native(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions( array( 'live' => 0 ) );

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Stay on corpus.' ) ) );

		$this->assertNotNull( $transporter->last_request );
		$this->assertStringContainsString( '/server/query', $transporter->last_request->getUri() );
		$this->assertSame( 0, $transporter->last_request->getData()['live'] );
	}

	public function test_endpoint_option_forces_openai_even_with_grounding(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions(
			array(
				'dataset'                            => array( 'agency_policy_docs' ),
				AskSageTextGenerationModel::ENDPOINT_OPTION => 'openai',
			)
		);

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Force OpenAI.' ) ) );

		$this->assertNotNull( $transporter->last_request );
		$this->assertStringContainsString( '/server/openai/v1/', $transporter->last_request->getUri() );
	}

	public function test_grounded_structured_output_raises_debug_notice_and_stays_on_native(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions( array( 'dataset' => array( 'agency_policy_docs' ) ) );
		$model->getConfig()->setOutputSchema( array( 'type' => 'object' ) );
		$model->getConfig()->setOutputMimeType( 'application/json' );

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$this->assertStringContainsString( '/server/query', $transporter->last_request->getUri() );
		$this->assertArrayNotHasKey( 'response_format', $transporter->last_request->getData() );

		$messages = implode(
			' ',
			array_column( $GLOBALS['ai_provider_for_ask_sage_test_errors'], 'message' )
		);
		$this->assertStringContainsString( 'outputSchema', $messages );
		$this->assertStringContainsString( 'outputMimeType', $messages );
	}

	public function test_grounded_request_without_openai_options_does_not_warn(): void {
		list( $model ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions( array( 'dataset' => array( 'agency_policy_docs' ) ) );

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Q' ) ) );

		$this->assertSame( array(), $GLOBALS['ai_provider_for_ask_sage_test_errors'] );
	}

	public function test_endpoint_option_forces_native_without_grounding(): void {
		list( $model, $transporter ) = ProviderFixtures::routing_model();
		$model->getConfig()->setCustomOptions(
			array( AskSageTextGenerationModel::ENDPOINT_OPTION => 'native' )
		);

		$model->generateTextResult( array( ProviderFixtures::user_message( 'Force native.' ) ) );

		$this->assertNotNull( $transporter->last_request );
		$this->assertStringContainsString( '/server/query', $transporter->last_request->getUri() );
	}
}
