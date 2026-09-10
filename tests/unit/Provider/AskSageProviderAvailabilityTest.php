<?php
/**
 * Tests for provider availability.
 *
 * @package WordPressVIP\AiProviderForAskSage
 */

declare( strict_types=1 );

namespace WordPressVIP\AiProviderForAskSage\Tests\Unit\Provider;

use WordPressVIP\AiProviderForAskSage\Provider\AskSageProviderAvailability;
use WordPressVIP\AiProviderForAskSage\Tests\Unit\TestCase;

/**
 * @covers \WordPressVIP\AiProviderForAskSage\Provider\AskSageProviderAvailability
 */
class AskSageProviderAvailabilityTest extends TestCase {

	public function test_is_configured_when_api_key_is_present(): void {
		putenv( 'ASK_SAGE_API_KEY=present' );

		$availability = new AskSageProviderAvailability();

		$this->assertTrue( $availability->isConfigured() );
	}

	public function test_is_not_configured_without_api_key(): void {
		$availability = new AskSageProviderAvailability();

		$this->assertFalse( $availability->isConfigured() );
	}
}
