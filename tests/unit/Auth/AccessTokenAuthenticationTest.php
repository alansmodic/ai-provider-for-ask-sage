<?php
/**
 * Tests for Ask Sage request authentication.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Auth;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication;
use WordPressVIP\AiProviderForAskSage\Tests\Support\ProviderFixtures;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Auth\AccessTokenAuthentication
 */
class AccessTokenAuthenticationTest extends TestCase {

	public function test_native_query_uses_access_token_header(): void {
		$auth    = new AccessTokenAuthentication( ProviderFixtures::API_KEY );
		$request = $auth->authenticateRequest(
			new Request( HttpMethodEnum::POST(), 'https://api.asksage.ai/server/query' )
		);

		$this->assertSame( ProviderFixtures::API_KEY, $request->getHeaderAsString( 'x-access-tokens' ) );
		$this->assertNull( $request->getHeader( 'Authorization' ) );
	}

	public function test_openai_surface_uses_bearer_only(): void {
		$auth    = new AccessTokenAuthentication( ProviderFixtures::API_KEY );
		$request = $auth->authenticateRequest(
			new Request( HttpMethodEnum::POST(), 'https://api.asksage.ai/server/openai/v1/chat/completions' )
		);

		$this->assertSame( 'Bearer ' . ProviderFixtures::API_KEY, $request->getHeaderAsString( 'Authorization' ) );
		$this->assertNull( $request->getHeader( 'x-access-tokens' ) );
	}

	public function test_empty_api_key_does_not_add_headers(): void {
		$auth    = new AccessTokenAuthentication( '' );
		$request = $auth->authenticateRequest(
			new Request( HttpMethodEnum::POST(), 'https://api.asksage.ai/server/query' )
		);

		$this->assertNull( $request->getHeader( 'x-access-tokens' ) );
		$this->assertNull( $request->getHeader( 'Authorization' ) );
	}
}
