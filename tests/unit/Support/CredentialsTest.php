<?php
/**
 * Tests for credential and endpoint resolution.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Support;

use WordPressVIP\AiProviderForAskSage\Support\Credentials;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Support\Credentials
 */
class CredentialsTest extends TestCase {

	public function test_unconfigured_base_url_uses_commercial_default(): void {
		$this->assertSame( Credentials::DEFAULT_BASE_URL, Credentials::base_url() );
	}

	public function test_govcloud_https_url_is_accepted_and_trimmed(): void {
		putenv( 'ASK_SAGE_BASE_URL=https://api.agency.example.ai/' );

		$this->assertSame( 'https://api.agency.example.ai', Credentials::base_url() );
	}

	/**
	 * @dataProvider invalid_base_urls
	 *
	 * @param string $url Invalid URL.
	 */
	public function test_invalid_base_url_fails_closed( string $url ): void {
		putenv( 'ASK_SAGE_BASE_URL=' . $url );

		$this->assertSame( '', Credentials::base_url() );
	}

	/**
	 * Invalid ASK_SAGE_BASE_URL values.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function invalid_base_urls(): array {
		return array(
			'http'      => array( 'http://api.asksage.ai' ),
			'userinfo'  => array( 'https://user:pass@api.asksage.ai' ),
			'not-a-url' => array( 'not-a-url' ),
		);
	}

	public function test_api_key_from_environment(): void {
		putenv( 'ASK_SAGE_API_KEY=from-env' );

		$this->assertSame( 'from-env', Credentials::api_key() );
	}

	public function test_missing_api_key_is_empty_string(): void {
		$this->assertSame( '', Credentials::api_key() );
	}
}
